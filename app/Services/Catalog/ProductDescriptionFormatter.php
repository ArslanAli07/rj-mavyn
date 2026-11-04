<?php

namespace App\Services\Catalog;

use Illuminate\Support\Str;

/**
 * Turns the supplier's plain-text product description into storefront content.
 *
 * The supplier writes every description with the same conventions: bullet lists
 * for the selling points, a bare line for the section heading and a final
 * "Product Specifications" block of "Label: value" lines. This parser keeps
 * that structure instead of flattening it into one blob of text.
 */
final class ProductDescriptionFormatter
{
    /**
     * @return array{html: string, short: string, specifications: array<int, array{label: string, value: string}>}
     */
    public function format(?string $description, string $title): array
    {
        $description = $this->normalise((string) $description);
        $blocks = $this->blocks($description);

        $html = [];
        $specifications = [];
        $short = '';

        foreach ($blocks as $block) {
            $heading = $block['heading'];
            $bullets = $block['bullets'];
            $paragraph = $block['paragraph'];

            if ($heading !== null) {
                // The export opens some descriptions with a full sentence, so
                // those lines read as copy rather than a section title.
                if ($this->isSentence($heading)) {
                    $paragraph = $paragraph === null ? $heading : $paragraph.' '.$heading;
                } else {
                    $html[] = '<h2>'.e($heading).'</h2>';
                }
            }

            if ($this->isSpecificationHeading($heading) || $this->looksLikeSpecificationBlock($heading, $bullets)) {
                foreach ($bullets as $bullet) {
                    $pair = $this->specification($bullet);
                    if ($pair !== null) {
                        $specifications[] = $pair;
                        continue;
                    }
                    $html[] = '<ul><li>'.e($bullet).'</li></ul>';
                }
                continue;
            }

            if ($bullets !== []) {
                $items = '';
                foreach ($bullets as $bullet) {
                    $items .= '<li>'.e($bullet).'</li>';
                    if ($short === '') {
                        $short = $this->summarise($bullet);
                    }
                }
                $html[] = '<ul>'.$items.'</ul>';
            }

            if ($paragraph !== null) {
                $html[] = '<p>'.e($paragraph).'</p>';
                if ($short === '') {
                    $short = $this->summarise($paragraph);
                }
            }
        }

        if ($html === []) {
            $html[] = '<p>'.e($title).'</p>';
            $short = $title;
        }

        return [
            'html' => implode("\n", $html),
            'short' => $short !== '' ? $short : $title,
            'specifications' => $specifications,
        ];
    }

    private function normalise(string $description): string
    {
        return str_replace(["\r\n", "\r"], "\n", trim($description));
    }

    /**
     * Split the description into blocks of "heading + bullets" and paragraphs.
     *
     * @return array<int, array{heading: string|null, bullets: array<int, string>, paragraph: string|null}>
     */
    private function blocks(string $description): array
    {
        $blocks = [];
        $current = ['heading' => null, 'bullets' => [], 'paragraph' => null];

        $flush = function () use (&$blocks, &$current) {
            if ($current['heading'] !== null || $current['bullets'] !== [] || $current['paragraph'] !== null) {
                $blocks[] = $current;
            }
            $current = ['heading' => null, 'bullets' => [], 'paragraph' => null];
        };

        foreach (explode("\n", $description) as $line) {
            $line = trim($line);
            if ($line === '') {
                $flush();
                continue;
            }
            // The export sometimes leaves a single dash where a heading belongs.
            if (preg_match('/^[-–—\s]{1,4}$/', $line)) {
                continue;
            }
            if (str_starts_with($line, '- ') || str_starts_with($line, '– ')) {
                $current['bullets'][] = trim(substr($line, 2));
                continue;
            }
            if ($current['bullets'] === [] && $current['paragraph'] === null) {
                $current['heading'] = rtrim($line, ':');
                continue;
            }
            // A free-standing line after bullets acts as the next heading.
            if ($current['paragraph'] === null) {
                $flush();
                $current['heading'] = rtrim($line, ':');
                continue;
            }
            $current['paragraph'] = trim(($current['paragraph'] ?? '').' '.$line);
        }
        $flush();

        return $blocks;
    }

    private function isSpecificationHeading(?string $heading): bool
    {
        return $heading !== null && str_contains(strtolower($heading), 'specification');
    }

    /**
     * The export opens some descriptions with a full sentence instead of a
     * section title, so those lines read as copy rather than a heading.
     */
    private function isSentence(?string $heading): bool
    {
        if ($heading === null) {
            return false;
        }
        $words = str_word_count($heading);

        return $words > 6 || str_ends_with($heading, '.');
    }

    /**
     * @param  array<int, string>  $bullets
     */
    private function looksLikeSpecificationBlock(?string $heading, array $bullets): bool
    {
        if ($bullets === []) {
            return false;
        }
        $pairs = 0;
        foreach ($bullets as $bullet) {
            if ($this->specification($bullet) !== null) {
                $pairs++;
            }
        }

        return $pairs === count($bullets) && $pairs >= 3;
    }

    /** @return array{label: string, value: string}|null */
    private function specification(string $bullet): ?array
    {
        if (! str_contains($bullet, ':')) {
            return null;
        }
        [$label, $value] = explode(':', $bullet, 2);
        $label = trim($label);
        $value = trim($value);
        if ($label === '' || $value === '') {
            return null;
        }
        if (mb_strlen($label) > 60) {
            return null;
        }

        return ['label' => $label, 'value' => $value];
    }

    private function summarise(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        if (mb_strlen($text) <= 180) {
            return $text;
        }

        return Str::limit($text, 177, '…');
    }
}
