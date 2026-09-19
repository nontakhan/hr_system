// Shared DataTables adapter: only the visible page is transferred/rendered.
const serverTableStates = new WeakMap();

function pagedTableUrl(base, request) {
    const url = new URL(base, window.location.href);
    for (const key of ['draw', 'start', 'length']) url.searchParams.set(key, request[key]);
    url.searchParams.set('search[value]', request.search?.value || '');
    (request.order || []).forEach((item, index) => {
        url.searchParams.set('order[' + index + '][column]', item.column);
        url.searchParams.set('order[' + index + '][dir]', item.dir);
    });
    return url;
}

function serverTableCells(html) {
    const body = document.createElement('tbody');
    body.innerHTML = html;
    const cells = Array.from(body.firstElementChild?.cells || []);
    const values = cells.map(cell => cell.innerHTML);
    values.cellClasses = cells.map(cell => cell.className);
    return values;
}

function loadServerTable(options) {
    const element = document.getElementById(options.tableId);
    if (!element || !window.jQuery || !jQuery.fn.DataTable) return Promise.resolve();
    const existing = serverTableStates.get(element);
    if (existing) {
        existing.options = options;
        const ready = new Promise(resolve => existing.waiters.push(resolve));
        existing.table.ajax.reload(null, true);
        return ready;
    }
    const state = { options, waiters: [], sequence: 0, controller: null, table: null };
    const ready = new Promise(resolve => state.waiters.push(resolve));
    serverTableStates.set(element, state);
    element.querySelector('tbody').innerHTML = '';
    const columnCount = element.querySelectorAll('thead th').length;
    state.table = jQuery(element).DataTable({
        serverSide: true, processing: true, searchDelay: 300,
        pageLength: 10, deferRender: true, autoWidth: false,
        order: options.order || [[0, 'desc']],
        columns: Array.from({ length: columnCount }, (_, index) => ({ data: index })),
        columnDefs: [{ targets: options.unsortable || [-1], orderable: false, searchable: false }],
        language: {
            processing: 'กำลังโหลด...', lengthMenu: 'แสดง _MENU_ รายการ ต่อหน้า',
            zeroRecords: 'ไม่พบข้อมูลที่ตรงกัน', emptyTable: 'ไม่พบข้อมูล',
            info: 'แสดง _START_ ถึง _END_ จากทั้งหมด _TOTAL_ รายการ',
            infoEmpty: 'แสดง 0 ถึง 0 จากทั้งหมด 0 รายการ', infoFiltered: '(กรองจากทั้งหมด _MAX_ รายการ)',
            search: 'ค้นหา:', paginate: { first: 'หน้าแรก', last: 'สุดท้าย', next: 'ถัดไป', previous: 'ก่อนหน้า' },
        },
        createdRow(row, values) {
            values.cellClasses.forEach((name, index) => { if (name) row.cells[index].className = name; });
        },
        ajax: async (request, callback) => {
            const sequence = ++state.sequence;
            state.controller?.abort();
            state.controller = new AbortController();
            try {
                const base = typeof state.options.url === 'function' ? state.options.url() : state.options.url;
                const response = await fetch(pagedTableUrl(base, request), { signal: state.controller.signal });
                const result = await response.json();
                if (sequence !== state.sequence) return;
                if (result.status !== 'success') throw new Error(result.message || 'โหลดข้อมูลไม่สำเร็จ');
                state.options.onResult?.(result);
                const rows = result.data.map(item => serverTableCells(state.options.renderRow(item)));
                callback({ draw: request.draw, recordsTotal: result.recordsTotal, recordsFiltered: result.recordsFiltered, data: rows });
                element.parentElement.querySelector('[data-table-error]')?.remove();
            } catch (error) {
                if (sequence !== state.sequence) return;
                callback({ draw: request.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                let notice = element.parentElement.querySelector('[data-table-error]');
                if (!notice) {
                    notice = document.createElement('div');
                    notice.dataset.tableError = 'true';
                    notice.className = 'alert alert-danger';
                    element.before(notice);
                }
                notice.textContent = error.message || 'โหลดข้อมูลไม่สำเร็จ';
            } finally {
                if (sequence === state.sequence) state.waiters.splice(0).forEach(resolve => resolve());
            }
        },
    });
    return ready;
}
