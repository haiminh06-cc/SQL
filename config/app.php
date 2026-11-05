<?php
declare(strict_types=1);

// Quy ước demo dùng chung cho form PHP và giao diện tích hợp; không phải cấu hình production.
return [
    'timezone' => 'Asia/Ho_Chi_Minh',
    'base_path' => '',
    'rules' => [
        'price_unit' => 'hour',
        'pitch_active' => 'HOAT_DONG',
        'pitch_maintenance' => 'BAO_TRI',
        'booking_new' => 'DA_DAT',
        'booking_cancelled' => 'DA_HUY',
        'booking_completed' => 'HOAN_THANH',
        'booking_received' => 'DANG_SU_DUNG',
        'invoice_unpaid' => 'CHUA_THANH_TOAN',
        'invoice_paid' => 'DA_THANH_TOAN',
        'one_invoice_per_booking' => true,
        'repeat_service' => 'increment_keep_price',
    ],
];
