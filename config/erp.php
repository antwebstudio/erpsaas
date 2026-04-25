<?php

return [
    'hide_tax_and_adjustment_fields' => env('HIDE_TAX_AND_ADJUSTMENT_FIELDS', false),
    'show_expiry_date' => env('SHOW_EXPIRY_DATE', true),
    'async_pdf_generation' => env('ASYNC_PDF_GENERATION', true),
    'hide_estimate_in_navigation' => env('HIDE_ESTIMATE_IN_NAVIGATION', false),
    'hide_contract_in_navigation' => env('HIDE_CONTRACT_IN_NAVIGATION', false),
    'require_lead_email_and_contact' => env('REQUIRE_LEAD_EMAIL_AND_CONTACT', false),
    'hide_item_name' => env('HIDE_ITEM_NAME', false),
    'hide_document_terms' => env('HIDE_DOCUMENT_TERMS', false),
    'hide_document_notes' => env('HIDE_DOCUMENT_NOTES', false),
    'hide_document_footer' => env('HIDE_DOCUMENT_FOOTER', false),
    'erp_system_company_id' => env('ERP_SYSTEM_COMPANY_ID'),
    'allow_edit_group_header' => env('ALLOW_EDIT_GROUP_HEADER', false),
    'allow_edit_sub_group_header' => env('ALLOW_EDIT_SUB_GROUP_HEADER', false),
    'hide_add_item_for_group' => env('HIDE_ADD_ITEM_FOR_GROUP', false),
    'hide_add_item_for_sub_group' => env('HIDE_ADD_ITEM_FOR_SUB_GROUP', false),
    'hide_invoice_save_button' => env('HIDE_INVOICE_SAVE_BUTTON', false),
];




