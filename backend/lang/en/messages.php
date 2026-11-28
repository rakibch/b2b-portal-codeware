<?php

return [
    'errors' => [
        'unauthenticated' => 'Please sign in to continue.',
        'forbidden' => 'You are not allowed to perform this action.',
        'not_found' => 'The requested resource was not found.',
        'too_many_requests' => 'Too many attempts. Please wait a moment and try again.',
        'account_inactive' => 'Your account has been deactivated. Please contact your company administrator.',
        'kyc_pending' => 'Your company verification is pending. Wholesale prices and ordering unlock after approval.',
        'kyc_rejected' => 'Your company verification was rejected. Please review the reason and resubmit your documents.',
        'kyc_suspended' => 'Your company account is suspended. Please contact your account manager.',
        'kyc_approved' => 'Your company is approved.',
        'kyc_invalid_transition' => 'This action is not allowed while the company is :status.',
        'kyc_documents_locked' => 'Documents can only be submitted while verification is pending or after a rejection.',
        'cart_empty_request' => 'Please add at least one item with a quantity.',
        'product_unavailable' => 'One or more products are no longer available.',
        'cart_lines_invalid' => 'Some items do not meet the ordering rules. Please review the highlighted lines.',
        'import_too_many_rows' => 'The file has too many rows. The maximum is :max.',
        'import_invalid_headers' => 'The file must have "sku" and "quantity" columns in the first row.',
        'import_empty' => 'The file does not contain any rows.',
        'quick_order_unmatched' => 'Some rows could not be matched to a product. Please fix or remove them.',
        'email_already_registered' => 'This email address is already registered.',
        'invitation_already_accepted' => 'This invitation has already been accepted.',
        'invitation_invalid' => 'This invitation link is invalid or has expired.',
        'last_company_admin' => 'Your company must keep at least one active administrator.',
        'address_last_billing' => 'You cannot delete the only billing address.',
        'address_last_shipping' => 'You cannot delete the only delivery address.',
        'rfq_lines_invalid' => 'Some requested quantities do not meet the ordering rules.',
        'rfq_quote_incomplete' => 'Please provide a quoted price for every item in the request.',
        'rfq_validity_past' => 'The quote validity date cannot be in the past.',
        'rfq_price_invalid' => 'Quoted prices must be greater than zero.',
        'rfq_invalid_transition' => 'This action is not allowed while the quote request is :status.',
        'rfq_expired' => 'This quotation has expired. Please submit a new request.',
        'stock_negative' => 'Stock cannot go below zero. Currently available: :available.',
        'tier_below_moq' => 'A tier starting at :min is below the minimum order quantity (:moq).',
        'tier_range_invalid' => 'Tier range :min – :max is invalid.',
        'tier_overlap' => 'Price tiers overlap near quantity :min.',
        'category_in_use' => 'This category still has products or sub-categories.',
        'unit_in_use' => 'This unit is used by products and cannot be deleted.',
        'customer_group_in_use' => 'This customer group is assigned to companies and cannot be deleted.',
    ],

    'pricing' => [
        'product_unavailable' => 'This product is not available.',
        'below_moq' => 'Minimum order quantity is :moq.',
        'invalid_step' => 'Quantity must be :moq plus multiples of :step.',
        'above_max' => 'Maximum order quantity is :max.',
        'insufficient_stock' => 'Only :available available in stock.',
    ],

    'quick_order' => [
        'missing_sku' => 'SKU is missing.',
        'invalid_quantity' => 'Quantity must be a whole number greater than zero.',
        'not_found' => 'No active product found with this SKU.',
    ],

    'auth' => [
        'logged_out' => 'You have been signed out.',
        'logged_out_everywhere' => 'You have been signed out from all devices.',
        'locale_updated' => 'Language preference saved.',
        'password_changed' => 'Your password has been changed. Other sessions were signed out.',
        'reset_link_sent' => 'If an account exists for this email, a password reset link has been sent.',
        'invitation_accepted' => 'Welcome aboard! Your account is ready.',
    ],

    'kyc' => [
        'submitted' => 'Registration received. Our team will verify your documents shortly.',
        'resubmitted' => 'Documents submitted for review.',
        'approved' => ':company has been approved.',
        'rejected' => ':company has been rejected.',
        'suspended' => ':company has been suspended.',
    ],

    'team' => [
        'invitation_sent' => 'Invitation sent to :email.',
        'invitation_revoked' => 'Invitation revoked.',
    ],

    'rfq' => [
        'submitted' => 'Quote request :reference submitted.',
        'quoted' => 'Quotation sent for :reference.',
    ],

    'company' => [
        'terms_updated' => 'Credit and commercial terms updated.',
    ],

    'inventory' => [
        'adjusted' => 'Stock adjusted.',
    ],

    'common' => [
        'deleted' => 'Deleted successfully.',
    ],

    'attributes' => [
        'company_name' => 'company name',
        'trade_license_no' => 'trade license number',
        'tin_no' => 'TIN number',
        'bin_no' => 'BIN number',
        'email' => 'email',
        'password' => 'password',
    ],

    'validation' => [
        'account_manager_invalid' => 'The selected account manager is not an active sales manager.',
    ],
];
