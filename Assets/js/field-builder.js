var CustomObjectsFieldBuilder = (function () {
    'use strict';
    var config = { bodyId: 'fields-body', addBtnId: 'add-field-row', formId: 'custom-object-form', fieldTypes: ['string', 'integer', 'text', 'boolean', 'datetime', 'float', 'bigint'] };
    var rowIndex = 0;
    function typeOptionsHtml() {
        return config.fieldTypes.map(function (t) { return '<option value="' + t + '">' + t + '</option>'; }).join('');
    }
    function createRow() {
        var idx = rowIndex++;
        var tr = document.createElement('tr');
        tr.setAttribute('data-row', String(idx));
        tr.innerHTML = '<td><input type="text" name="fields[' + idx + '][name]" class="form-control input-sm" pattern="[a-z][a-z0-9_]*" required placeholder="car_model"></td>'
            + '<td><input type="text" name="fields[' + idx + '][label]" class="form-control input-sm" placeholder="Car Model"></td>'
            + '<td><select name="fields[' + idx + '][type]" class="form-control input-sm">' + typeOptionsHtml() + '</select></td>'
            + '<td><button type="button" class="btn btn-xs btn-danger remove-field-row" title="Remove"><i class="fa fa-times"></i></button></td>';
        return tr;
    }
    function onRemove(e) {
        var btn = e.target.closest('.remove-field-row');
        if (!btn) return;
        var tr = btn.closest('tr');
        if (tr) tr.parentNode.removeChild(tr);
    }
    function onAdd() {
        var body = document.getElementById(config.bodyId);
        if (body) body.appendChild(createRow());
    }
    function onSubmit(e) {
        var form = document.getElementById(config.formId);
        if (!form || form.getAttribute('data-json-submit') !== '1') return;
        e.preventDefault();
        var name = (form.querySelector('[name="name"]') || {}).value || '';
        var singular = (form.querySelector('[name="singular"]') || {}).value || '';
        var plural = (form.querySelector('[name="plural"]') || {}).value || '';
        var fields = [];
        form.querySelectorAll('#' + config.bodyId + ' tr').forEach(function (tr) {
            var n = tr.querySelector('[name*="[name]"]');
            var l = tr.querySelector('[name*="[label]"]');
            var t = tr.querySelector('[name*="[type]"]');
            if (n && n.value) fields.push({ name: n.value.trim(), label: (l && l.value) ? l.value.trim() : n.value.trim(), type: (t && t.value) ? t.value : 'string' });
        });
        fetch(form.action, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ name: name, singular: singular, plural: plural, fields: fields }) })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (res) { if (res.ok) window.location.href = form.getAttribute('data-success-url') || './'; else alert(res.data.error || 'Save failed'); })
            .catch(function (err) { alert(err.message); });
    }
    function init(opts) {
        if (opts) Object.keys(opts).forEach(function (k) { config[k] = opts[k]; });
        var body = document.getElementById(config.bodyId);
        var addBtn = document.getElementById(config.addBtnId);
        var form = document.getElementById(config.formId);
        if (body && body.children.length === 0) body.appendChild(createRow());
        if (addBtn) addBtn.addEventListener('click', onAdd);
        if (body) body.addEventListener('click', onRemove);
        if (form) form.addEventListener('submit', onSubmit);
    }
    return { init: init, createRow: createRow };
})();
if (typeof module !== 'undefined' && module.exports) module.exports = CustomObjectsFieldBuilder;
