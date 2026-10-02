<?php

namespace App\Enums;

enum LeadStatus: string
{
    case Discovered = 'discovered';
    case Analyzing = 'analyzing';
    case Qualified = 'qualified';
    case Rejected = 'rejected';
    case ReadyForReview = 'ready_for_review';
    case Approved = 'approved';
    case Contacted = 'contacted';
    case Replied = 'replied';
    case Interested = 'interested';
    case NotInterested = 'not_interested';
    case Unsubscribed = 'unsubscribed';
    case Bounced = 'bounced';
}
