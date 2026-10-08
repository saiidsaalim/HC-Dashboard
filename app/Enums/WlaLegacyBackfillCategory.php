<?php

namespace App\Enums;

enum WlaLegacyBackfillCategory: string
{
    case Ready = 'ready';
    case AlreadyMigrated = 'already_migrated';
    case NeedsReview = 'needs_review';
    case Invalid = 'invalid';
    case Conflict = 'conflict';

    public function requiresReview(): bool
    {
        return in_array($this, [self::NeedsReview, self::Invalid, self::Conflict], true);
    }
}
