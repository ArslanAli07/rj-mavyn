// Execute the actual rendered checkout component with minimal DOM stubs; this is not a browser test.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const html = fs.readFileSync(process.argv[2], 'utf8');
const script = [...html.matchAll(/<script[^>]*>([\s\S]*?)<\/script>/g)].map(match => match[1]).find(value => value.includes("Alpine.data('checkoutForm'"));
assert.ok(script, 'Rendered checkout component missing');
let factory;
vm.runInNewContext(script, {
    document: { addEventListener: (_event, callback) => callback() },
    Alpine: { data: (name, value) => { assert.equal(name, 'checkoutForm'); factory = value; } },
});
const component = factory();
Object.assign(component.form, { firstName: 'Test', lastName: 'Buyer', email: 'buyer@example.com', phone: '03001234567', deliveryZoneId: Object.keys(component.zones)[0], addressLine: '1 Main Street', city: 'Sialkot', paymentMethod: 'cod' });
let files = [];
const form = { querySelector: () => ({ files }) };
assert.equal(component.receiptRequired, false);
assert.equal(component.validateFields(form), true);
for (const method of ['easypaisa', 'jazzcash', 'bank_transfer']) {
    component.form.paymentMethod = method;
    assert.equal(component.receiptRequired, true);
    assert.equal(component.validateFields(form), false);
    assert.ok(component.errors.payment_receipt);
    files = [{}];
    assert.equal(component.validateFields(form), true);
    files = [];
}
assert.equal(component.deliveryFee, 2.25);
assert.equal(component.grandTotal, 14.75);
component.form.deliveryZoneId = '';
assert.equal(component.validateFields(form), false);
assert.ok(component.errors.delivery_zone_id);
console.log('Rendered checkout JavaScript: payment receipt policies, validation, delivery fee, and total pass.');
