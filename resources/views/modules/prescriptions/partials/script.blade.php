@once
    @push('scripts')
        <script>
            if (! window.prescriptionDesk) {
                window.prescriptionDesk = function(config) {
                    return {
                        prescriptionModalOpen: config.prescriptionModalOpen,
                        prescriptionMode: config.prescriptionMode,
                        itemModalOpen: config.itemModalOpen,
                        itemMode: config.itemMode,
                        dispenseModalOpen: config.dispenseModalOpen,
                        closeModalOpen: config.closeModalOpen,
                        overrideModalOpen: config.overrideModalOpen,
                        prescriptionStoreAction: config.prescriptionStoreAction,
                        prescriptionUpdateBase: config.prescriptionUpdateBase,
                        itemStoreAction: config.itemStoreAction,
                        itemUpdateBase: config.itemUpdateBase,
                        itemActionBase: config.itemActionBase,
                        defaultVisitId: config.defaultVisitId,
                        defaultPrescriptionId: config.defaultPrescriptionId,
                        prescriptionForm: config.prescriptionForm,
                        itemForm: config.itemForm,
                        dispenseForm: config.dispenseForm,
                        closeForm: config.closeForm,
                        overrideForm: config.overrideForm,
                        dispenseMeta: null,
                        closeMeta: null,
                        overrideMeta: null,
                        parsePayload(payload) {
                            try {
                                return JSON.parse(payload || '{}');
                            } catch (error) {
                                return {};
                            }
                        },
                        emptyIngredients() {
                            return [
                                { medicine_id: '', quantity_required: '', unit: '' },
                                { medicine_id: '', quantity_required: '', unit: '' },
                                { medicine_id: '', quantity_required: '', unit: '' },
                            ];
                        },
                        normalizeIngredients(rows) {
                            const base = this.emptyIngredients();

                            (rows || []).slice(0, 3).forEach((row, index) => {
                                base[index] = {
                                    medicine_id: `${row.medicine_id ?? ''}`,
                                    quantity_required: `${row.quantity_required ?? ''}`,
                                    unit: row.unit ?? '',
                                };
                            });

                            return base;
                        },
                        emptyPrescription() {
                            return {
                                id: null,
                                visit_registration_id: this.defaultVisitId,
                                notes: '',
                            };
                        },
                        emptyItem() {
                            return {
                                id: null,
                                prescription_id: this.defaultPrescriptionId,
                                item_type: 'in_house',
                                medicine_id: '',
                                display_name: '',
                                route: '',
                                dose_amount: '',
                                dose_unit: '',
                                frequency: '',
                                duration_days: '',
                                instruction: '',
                                quantity_prescribed: 1,
                                dispense_unit: '',
                                weight_snapshot_kg: '',
                                status: 'pending',
                                notes: '',
                                compound_ingredients: this.emptyIngredients(),
                            };
                        },
                        openCreatePrescription(visitId = null) {
                            this.prescriptionMode = 'create';
                            this.prescriptionForm = this.emptyPrescription();
                            this.prescriptionForm.visit_registration_id = visitId || this.defaultVisitId;
                            this.prescriptionModalOpen = true;
                        },
                        openEditPrescription(payload) {
                            this.prescriptionMode = 'update';
                            this.prescriptionForm = this.parsePayload(payload);
                            this.prescriptionModalOpen = true;
                        },
                        openCreateItem(prescriptionId = null) {
                            this.itemMode = 'create';
                            this.itemForm = this.emptyItem();
                            this.itemForm.prescription_id = prescriptionId || this.defaultPrescriptionId;
                            this.itemModalOpen = true;
                        },
                        openEditItem(payload) {
                            const parsed = this.parsePayload(payload);

                            this.itemMode = 'update';
                            this.itemForm = {
                                ...this.emptyItem(),
                                ...parsed,
                                compound_ingredients: this.normalizeIngredients(parsed.compound_ingredients || []),
                            };
                            this.itemModalOpen = true;
                        },
                        openDispense(payload) {
                            const parsed = this.parsePayload(payload);

                            this.dispenseMeta = parsed;
                            this.dispenseForm = {
                                id: parsed.id ?? null,
                                quantity_dispensed: parsed.remaining ?? '',
                                unit_price: parsed.preview?.selling_price ?? '',
                                notes: '',
                            };
                            this.dispenseModalOpen = true;
                        },
                        openCloseRemaining(payload) {
                            const parsed = this.parsePayload(payload);

                            this.closeMeta = parsed;
                            this.closeForm = {
                                id: parsed.id ?? null,
                                closure_status: 'external',
                                closure_reason: '',
                            };
                            this.closeModalOpen = true;
                        },
                        openOverride(payload) {
                            const parsed = this.parsePayload(payload);

                            this.overrideMeta = parsed;
                            this.overrideForm = {
                                id: parsed.id ?? null,
                                override_reason: '',
                            };
                            this.overrideModalOpen = true;
                        },
                        closeAll() {
                            this.prescriptionModalOpen = false;
                            this.itemModalOpen = false;
                            this.dispenseModalOpen = false;
                            this.closeModalOpen = false;
                            this.overrideModalOpen = false;
                        },
                    };
                };
            }
        </script>
    @endpush
@endonce
