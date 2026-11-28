<?php

return [
    'greeting' => 'Hello :name,',
    'salutation' => 'Regards, :app',

    'kyc_submitted' => [
        'subject' => 'New KYC submission: :company',
        'body' => ':company has submitted business verification documents for review.',
        'action' => 'Review KYC',
    ],
    'kyc_approved' => [
        'subject' => 'Your business account is approved',
        'body' => 'Good news! :company is verified. You can now view wholesale prices and place orders.',
        'action' => 'Start ordering',
    ],
    'kyc_rejected' => [
        'subject' => 'Business verification needs attention',
        'body' => 'We could not verify :company. Reason: :reason. Please update your documents and resubmit.',
        'action' => 'Resubmit documents',
    ],
    'kyc_suspended' => [
        'subject' => 'Your business account has been suspended',
        'body' => 'The account for :company has been suspended. Reason: :reason.',
        'action' => 'View account',
    ],
    'rfq_submitted' => [
        'subject' => 'New quote request :reference',
        'body' => ':company has requested a quotation (:reference).',
        'action' => 'Prepare quote',
    ],
    'rfq_quoted' => [
        'subject' => 'Your quotation :reference is ready',
        'body' => 'We have prepared prices for your request :reference, valid until :valid_until.',
        'action' => 'View quotation',
    ],
    'rfq_declined' => [
        'subject' => 'Update on your quote request :reference',
        'body' => 'We are unable to quote request :reference. Note: :reason',
        'action' => 'View request',
    ],
    'rfq_responded' => [
        'subject' => 'Quote :reference was :status',
        'body' => ':company responded to quotation :reference: :status.',
        'action' => 'Open quote',
    ],
    'company_invitation' => [
        'subject' => 'You are invited to join :company',
        'body' => ':inviter has invited you to order for :company on our wholesale portal. This invitation expires in :hours hours.',
        'action' => 'Accept invitation',
    ],
];
