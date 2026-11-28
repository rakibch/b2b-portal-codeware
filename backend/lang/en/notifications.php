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
    'order_placed' => [
        'subject' => 'New order :number from :company',
        'body' => ':company placed order :number for :total.',
        'action' => 'Review order',
    ],
    'order_approval_requested' => [
        'subject' => 'Order :number needs your approval',
        'body' => ':name placed order :number for :total, which exceeds their approval limit.',
        'action' => 'Review & approve',
    ],
    'order_status_changed' => [
        'subject' => 'Order :number is now :status',
        'body' => 'Your order :number status changed to: :status.',
        'action' => 'Track order',
    ],
    'payment_submitted' => [
        'subject' => 'Payment :reference awaiting verification',
        'body' => ':company submitted a payment of :amount (:reference).',
        'action' => 'Verify payment',
    ],
    'payment_verified' => [
        'subject' => 'Payment :reference received',
        'body' => 'We have received and verified your payment of :amount (:reference). Thank you!',
        'action' => 'View statement',
    ],
    'payment_rejected' => [
        'subject' => 'Payment :reference could not be verified',
        'body' => 'Your payment of :amount (:reference) could not be verified. Reason: :reason',
        'action' => 'View payments',
    ],
    'invoice_overdue' => [
        'subject' => 'Invoice :number is overdue',
        'body' => 'Invoice :number with a balance of :amount was due on :due_date. Please arrange payment to keep your credit line active.',
        'action' => 'Pay now',
    ],
];
