<?php

namespace App\Jobs\Notifications;

/** Compatibility for already queued confirmation jobs. New dispatches use the generic job. */
class SendOrderConfirmation extends SendOutboxNotification {}
