<?php

if (!function_exists('action_button')) {
    function action_button(string $action, array $options = []) {
        /**
         * =========================
         * ICONS
         * =========================
         */
        $icons = [
            'add' => 'ph-plus',
            'edit' => 'ph-pencil-simple',
            'meetingQr' => 'ph-qr-code',

            'delete' => 'ph-trash',
            'restore' => 'ph-arrow-clockwise',
            'archive' => 'ph-archive',
            'forceDelete' => 'ph-trash',
            'toggleStatus' => 'ph-toggle-left',
            'approveOrganization' => 'ph-check-circle',
            'rejectOrganization' => 'ph-x',
            'suspendOrganization' => 'ph-pause',

            'publishEvent' => 'ph-rocket-launch',

            'acceptMeeting' => 'ph-check-circle',
            'rejectMeeting' => 'ph-x-circle',
            'cancelMeeting' => 'ph-x',
            'completeMeeting' => 'ph-check',
            'noShowMeeting' => 'ph-user-minus',

            'restoreArchive' => 'ph-arrow-counter-clockwise',

            'link' => 'ph-arrow-square-out',
            'view' => 'ph-eye',
            'addLink' => 'ph-plus',
            'viewModal' => 'ph-eye',

            'sendAccess' => 'ph-paper-plane-tilt',
            'resetAccess' => 'ph-key',
            'release' => 'ph-link-break',

            'activate'   => 'ph-check-circle',
            'deactivate' => 'ph-x-circle',
            'suspend'    => 'ph-prohibit',

            'verify'     => 'ph-check-circle',
            'unverify'   => 'ph-x-circle',
            'makeAdmin'   => 'ph-crow',
        ];

        /**
         * =========================
         * COLORS
         * =========================
         */
        $colors = [
            'add' => 'warning',
            'edit' => 'light',
            'meetingQr' => 'light text-dark',

            'delete' => 'light text-danger',
            'forceDelete' => 'light text-danger',
            'restore' => 'light text-info',
            'archive' => 'light text-warning',
            'toggleStatus' => 'light text-warning',

            'approveOrganization' => 'light text-success',
            'rejectOrganization' => 'light text-danger',
            'suspendOrganization' => 'light text-warning',

            'publishEvent' => 'light text-success',

            'acceptMeeting' => 'light text-success',
            'rejectMeeting' => 'light text-danger',
            'cancelMeeting' => 'light text-warning',
            'completeMeeting' => 'light text-primary',
            'noShowMeeting' => 'light text-secondary',

            'restoreArchive' => 'light text-info',

            'link' => 'light text-primary',
            'view' => 'light text-primary',
            'addLink' => 'warning',
            'viewModal' => 'light text-primary',

            'sendAccess' => 'light text-primary',
            'resetAccess' => 'light text-warning',
            'release' => 'light text-danger',

            'activate'   => 'success',
            'deactivate' => 'warning',
            'suspend'    => 'danger',

            'verify'     => 'primary',
            'unverify'   => 'warning',
            'makeAdmin'   => 'warning',
        ];

        /**
         * =========================
         * LABELS
         * =========================
         */
        $labels = [
            'add' => 'Add',
            'edit' => 'Edit',
            'meetingQr' => 'QR',

            'delete' => 'Delete',
            'restore' => 'Restore',
            'archive' => 'Archive',
            'forceDelete' => 'Delete',

            'toggleStatus' => 'Status',
            'approveOrganization' => 'Approve',
            'rejectOrganization' => 'Rejected',
            'suspendOrganization' => 'Suspened',

            'publishEvent' => 'Publish',

            'acceptMeeting' => 'Accept',
            'rejectMeeting' => 'Reject',
            'cancelMeeting' => 'Cancel',
            'completeMeeting' => 'Complete',
            'noShowMeeting' => 'No Show',

            'restoreArchive' => 'Restore',

            'link' => '',
            'view' => '',
            'addLink' => 'Add',
            'viewModal' => 'View',

            'sendAccess' => '',
            'resetAccess' => '',
            'release' => 'Release',

            'activate'   => 'Activate',
            'deactivate' => 'Deactivate',
            'suspend'    => 'Suspend',

            'verify'     => 'Verify',
            'unverify'   => 'Unverify',
            'makeAdmin'   => 'Make Admin',
        ];

        /**
         * =========================
         * VALUES
         * =========================
         */
        $icon = array_key_exists('icon', $options) ? $options['icon'] : ($icons[$action] ?? 'ph-circle');
        $color = $options['color'] ?? ($colors[$action] ?? 'light');

        $label = $options['label'] ?? ($labels[$action] ?? ucfirst($action));
        $title = $options['title'] ?? $label;

        /**
         * =========================
         * CUSTOM MODE FLAG
         * =========================
         */
        $btnType = $options['btn-type'] ?? '';

        /**
         * =========================
         * CUSTOM CLASS FLAG
         * =========================
         */
        $class = $options['class'] ?? '';

        /**
         * =========================
         * MODAL SIZE
         * =========================
         */
        $modalSize = $options['modal-size'] ?? 'modal-lg';

        /**
         * =========================
         * ATTRIBUTES
         * =========================
         */
        $url = $options['url'] ?? null;
        $href = $options['href'] ?? null;

        $createUrl = $options['createUrl'] ?? null;
        $storeUrl = $options['storeUrl'] ?? null;
        $editUrl = $options['editUrl'] ?? null;
        $updateUrl = $options['updateUrl'] ?? null;

        $routePlaceholder = $options['routePlaceholder'] ?? null;

        $table = $options['table'] ?? null;
        $stopPropagation = $options['stop-propagation'] ?? false;

        /**
         * =========================
         * LINK TYPES (NO JS)
         * =========================
         */
        $isLink = in_array($action, ['link','view','addLink']);
        if ($isLink) {
            $href = $href ?? $url ?? '#';
            $html = '<a href="' . e($href) . '" class="btn btn-sm btn-' . $color . ' ' . $class . ($btnType === 'btn-icon' ? ' btn-icon' : '') . '" title="' . e($title) . '"';

            if ($stopPropagation) {
                $html .= ' onclick="event.stopPropagation();"';
            }

            if ($routePlaceholder) {
                $html .= ' data-route-placeholder="' . e($routePlaceholder) . '"';
            }
            
            $html .= '>';
            
            if ($btnType === 'btn-icon' || empty($label)) {
                if (!empty($icon)) {
                    $html .= '<i class="' . $icon . ' ph-sm"></i>';
                }
            } else {
                if (!empty($icon)) {
                    $html .= '<i class="' . $icon . ' ph-sm me-1"></i>';
                }

                $html .= e($label);
            }
            $html .= '</a>';

            return $html;
        }

        /**
         * =========================
         * MODAL TYPES
         * =========================
         */
        $isModal = in_array($action, ['add', 'edit']);
        $isViewer = in_array($action, ['meetingQr', 'viewModal',]);

        $html = '<button type="button" 
            class="btn btn-sm btn-' . $color . ' ' . $class . ($btnType === 'btn-icon' ? ' btn-icon' : '') . '"
            data-action="' . $action . '" data-title="' . e($title) . '" title="' . e($title) . '"';

        if ($stopPropagation) {
            $html .= ' onclick="event.stopPropagation();"';
        }

        if ($isModal) {
            $html .= ' data-bs-toggle="modal" data-bs-target="#modal-dialog"';
            $html .= ' data-modal-size="' . e($modalSize) . '"';

            if ($createUrl) $html .= ' data-create-url="' . e($createUrl) . '"';
            if ($storeUrl) $html .= ' data-store-url="' . e($storeUrl) . '"';
            if ($editUrl) $html .= ' data-edit-url="' . e($editUrl) . '"';
            if ($updateUrl) $html .= ' data-update-url="' . e($updateUrl) . '"';
        }

        if ($isViewer) {
            $html .= ' data-bs-toggle="modal" data-bs-target="#modal-dialog"';
            $html .= ' data-modal-size="' . e($modalSize) . '"';
            
            if ($url) {
                $html .= ' data-url="' . e($url) . '"';
            }
        }

        if ($url) {
            $html .= ' data-url="' . e($url) . '"';
        }

        if (!empty($options['method'])) {
            $html .= ' data-method="' . e($options['method']) . '"';
        }

        if ($table) {
            $html .= ' data-table-reload="' . e($table) . '"';
        }
        $html .= '>';

        /**
         * =========================
         * BUTTON CONTENT
         * =========================
         */
        if ($btnType === 'btn-icon') {
            if (!empty($icon)) {
                $html .= '<i class="' . $icon . ' ph-sm"></i>';
            }
        } else {
            if (!empty($icon)) {
                $html .= '<i class="' . $icon . ' ph-sm me-1"></i>';
            }
            $html .= e($label);
        }
        $html .= '</button>';
        return $html;
    }
}