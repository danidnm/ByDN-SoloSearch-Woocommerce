// Add/remove rows for the Field Mapping grid (see Admin\FieldMappingField).
// Plain vanilla JS, no framework/build step - matches the rest of the plugin.
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var table = document.getElementById('ss-field-mapping-table');
        var template = document.getElementById('ss-field-mapping-row-template');
        var addButton = document.getElementById('ss-field-mapping-add');

        if (!table || !template || !addButton) {
            return;
        }

        var tbody = table.querySelector('tbody');

        addButton.addEventListener('click', function () {
            var row = template.content.firstElementChild.cloneNode(true);
            tbody.appendChild(row);
        });

        tbody.addEventListener('click', function (e) {
            if (e.target.classList.contains('ss-field-mapping-remove')) {
                e.target.closest('tr').remove();
            }
        });
    });
})();
