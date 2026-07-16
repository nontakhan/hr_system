(function () {
    'use strict';

    let activeController = null;
    let modalInstance = null;
    let warningTypesPromise = null;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    async function fetchJson(url, options = {}) {
        const response = await fetch(url, options);
        const text = await response.text();
        if (!text.trim()) throw new Error('เซิร์ฟเวอร์ไม่ส่งข้อมูลกลับ กรุณาลองใหม่อีกครั้ง');
        let payload;
        try {
            payload = JSON.parse(text);
        } catch (error) {
            throw new Error('ข้อมูลตอบกลับจากเซิร์ฟเวอร์ไม่ถูกต้อง');
        }
        if (!response.ok || payload.status !== 'success') {
            throw new Error(payload.message || 'ดำเนินการไม่สำเร็จ');
        }
        return payload.data;
    }

    function ensureModal() {
        let modal = document.getElementById('bulkEmployeeWarningModal');
        if (!modal) {
            document.body.insertAdjacentHTML('beforeend', `
                <div class="modal fade" id="bulkEmployeeWarningModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title"><i class="fas fa-triangle-exclamation me-2"></i>เพิ่มใบเตือนจากรายงาน</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="ปิด"></button>
                            </div>
                            <form id="bulkEmployeeWarningForm">
                                <div class="modal-body">
                                    <div class="alert alert-light border d-flex flex-wrap gap-4 mb-3" id="bulkEmployeeWarningCounts"></div>
                                    <div class="mb-3">
                                        <label class="form-label" for="bulkEmployeeWarningType">รายการใบเตือน <span class="text-danger">*</span></label>
                                        <select class="form-select" id="bulkEmployeeWarningType" required>
                                            <option value="">กำลังโหลดรายการใบเตือน...</option>
                                        </select>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label" for="bulkEmployeeWarningSharedNote">หมายเหตุใบเตือน</label>
                                        <textarea class="form-control" id="bulkEmployeeWarningSharedNote" rows="3" maxlength="2000" placeholder="ไม่บังคับ — หมายเหตุนี้จะใช้กับใบเตือนทุกใบที่เลือก"></textarea>
                                        <div class="form-text">ระบบจะสร้างรายละเอียดเหตุการณ์ของแต่ละใบให้อัตโนมัติ และต่อท้ายหมายเหตุนี้</div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                    <button id="bulkEmployeeWarningSubmitBtn" type="submit" class="btn btn-primary" disabled>
                                        <i class="fas fa-save me-1"></i> บันทึกใบเตือน
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>`);
            modal = document.getElementById('bulkEmployeeWarningModal');
            document.getElementById('bulkEmployeeWarningForm').addEventListener('submit', (event) => {
                event.preventDefault();
                activeController?.submit();
            });
            modal.addEventListener('hidden.bs.modal', () => {
                activeController = null;
            });
        }
        if (!modalInstance && window.bootstrap?.Modal) {
            modalInstance = window.bootstrap.Modal.getOrCreateInstance(modal);
        }
        return modal;
    }

    function loadWarningTypes() {
        if (!warningTypesPromise) {
            warningTypesPromise = fetchJson('api/employee_warning_api.php?action=get_warning_types')
                .catch((error) => {
                    warningTypesPromise = null;
                    throw error;
                });
        }
        return warningTypesPromise;
    }

    function create(config) {
        const selectedKeys = new Set();
        let rows = [];
        let eventsByKey = new Map();

        const actionButton = document.getElementById(config.actionButtonId);
        const countElement = document.getElementById(config.selectedCountId);

        function getSelectAllElement() {
            return document.getElementById(config.selectAllId);
        }

        function selectedEvents() {
            return [...selectedKeys].map((key) => eventsByKey.get(key)).filter(Boolean);
        }

        function updateModalCounts() {
            const events = selectedEvents();
            const uniqueEmployeeCount = new Set(events.map((event) => String(event.employee_id))).size;
            document.getElementById('bulkEmployeeWarningCounts').innerHTML = `
                <div><span class="text-muted">พนักงานที่เลือก</span> <strong class="ms-1">${uniqueEmployeeCount.toLocaleString('th-TH')} คน</strong></div>
                <div><span class="text-muted">จำนวนใบเตือน</span> <strong class="ms-1">${events.length.toLocaleString('th-TH')} ใบ</strong></div>`;
            const submitButton = document.getElementById('bulkEmployeeWarningSubmitBtn');
            submitButton.innerHTML = `<i class="fas fa-save me-1"></i> บันทึกใบเตือน ${events.length.toLocaleString('th-TH')} ใบ`;
            return events.length;
        }

        const controller = {
            replaceRows(nextRows) {
                rows = Array.isArray(nextRows) ? nextRows : [];
                eventsByKey = new Map();
                rows.forEach((row) => {
                    const event = config.buildEvent(row);
                    if (event?.source_key) eventsByKey.set(String(event.source_key), event);
                });
                [...selectedKeys].forEach((key) => {
                    const event = eventsByKey.get(key);
                    if (!event || event.already_warned) selectedKeys.delete(key);
                });
                controller.syncCheckboxes();
                controller.updateControls();
            },

            clearSelection() {
                selectedKeys.clear();
                controller.syncCheckboxes();
                controller.updateControls();
            },

            toggleKey(key, checked) {
                const event = eventsByKey.get(String(key));
                if (!event || event.already_warned) return;
                if (checked) selectedKeys.add(String(key));
                else selectedKeys.delete(String(key));
                controller.syncCheckboxes();
                controller.updateControls();
            },

            toggleAllEligible(checked) {
                rows.forEach((row) => {
                    const event = config.buildEvent(row);
                    if (!event?.source_key || event.already_warned) return;
                    if (checked) selectedKeys.add(String(event.source_key));
                    else selectedKeys.delete(String(event.source_key));
                });
                controller.syncCheckboxes();
                controller.updateControls();
            },

            syncCheckboxes() {
                const root = document.getElementById(config.pageId) || document;
                root.querySelectorAll('.employee-warning-row-select').forEach((checkbox) => {
                    const key = String(checkbox.dataset.warningSourceKey || '');
                    const event = eventsByKey.get(key);
                    checkbox.checked = selectedKeys.has(key);
                    checkbox.disabled = !event || Boolean(event.already_warned);
                });
                const selectAllElement = getSelectAllElement();
                if (selectAllElement) {
                    const eligible = [...eventsByKey.values()].filter((event) => !event.already_warned);
                    const selectedEligible = eligible.filter((event) => selectedKeys.has(String(event.source_key))).length;
                    selectAllElement.checked = eligible.length > 0 && selectedEligible === eligible.length;
                    selectAllElement.indeterminate = selectedEligible > 0 && selectedEligible < eligible.length;
                    selectAllElement.disabled = eligible.length === 0;
                }
            },

            updateControls() {
                if (countElement) countElement.textContent = selectedKeys.size.toLocaleString('th-TH');
                if (actionButton) actionButton.disabled = selectedKeys.size === 0;
            },

            async openModal() {
                if (!selectedKeys.size) return;
                activeController = controller;
                ensureModal();
                document.getElementById('bulkEmployeeWarningSharedNote').value = '';
                const submitButton = document.getElementById('bulkEmployeeWarningSubmitBtn');
                const typeSelect = document.getElementById('bulkEmployeeWarningType');
                const eventCount = updateModalCounts();
                submitButton.disabled = true;
                modalInstance?.show();
                try {
                    const types = await loadWarningTypes();
                    typeSelect.innerHTML = '<option value="">เลือกรายการใบเตือน</option>' + types.map((type) => (
                        `<option value="${Number(type.id)}">${escapeHtml(type.type_name || '-')}</option>`
                    )).join('');
                    submitButton.disabled = eventCount === 0 || types.length === 0;
                } catch (error) {
                    typeSelect.innerHTML = '<option value="">โหลดรายการใบเตือนไม่สำเร็จ</option>';
                    if (window.Swal) Swal.fire('ไม่สามารถโหลดรายการใบเตือนได้', error.message, 'error');
                }
            },

            async submit() {
                const typeSelect = document.getElementById('bulkEmployeeWarningType');
                const sharedNote = document.getElementById('bulkEmployeeWarningSharedNote').value;
                const submitButton = document.getElementById('bulkEmployeeWarningSubmitBtn');
                const warningTypeId = Number(typeSelect.value || 0);
                if (!warningTypeId) {
                    if (window.Swal) Swal.fire('กรุณาเลือกรายการใบเตือน', '', 'warning');
                    typeSelect.focus();
                    return;
                }
                const items = selectedEvents().map((event) => ({
                    source_type: event.source_type,
                    source_key: event.source_key,
                }));
                if (!items.length) return;
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังบันทึกใบเตือน...';
                try {
                    const result = await fetchJson('api/employee_warning_api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'bulk_create', warning_type_id: warningTypeId, shared_note: sharedNote, items }),
                    });
                    modalInstance?.hide();
                    config.onCompleted?.(result);
                    if (window.Swal) {
                        Swal.fire(
                            'บันทึกใบเตือนเรียบร้อยแล้ว',
                            `สำเร็จ ${Number(result.created_count || 0).toLocaleString('th-TH')} ใบ · ข้ามรายการซ้ำ ${Number(result.duplicate_count || 0).toLocaleString('th-TH')} รายการ`,
                            'success'
                        );
                    }
                } catch (error) {
                    if (window.Swal) Swal.fire('บันทึกใบเตือนไม่สำเร็จ', error.message, 'error');
                    else alert(error.message);
                } finally {
                    submitButton.disabled = false;
                    updateModalCounts();
                }
            },
        };

        actionButton?.addEventListener('click', () => controller.openModal());
        const page = document.getElementById(config.pageId);
        page?.addEventListener('click', (event) => {
            if (event.target.id === config.selectAllId) event.stopPropagation();
        }, true);
        page?.addEventListener('change', (event) => {
            if (event.target.id === config.selectAllId) {
                controller.toggleAllEligible(event.target.checked);
                return;
            }
            const checkbox = event.target.closest('.employee-warning-row-select');
            if (checkbox) controller.toggleKey(checkbox.dataset.warningSourceKey, checkbox.checked);
        });
        controller.updateControls();
        return controller;
    }

    window.EmployeeWarningBulk = { create };
})();
