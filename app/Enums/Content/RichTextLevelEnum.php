<?php

declare(strict_types=1);

namespace App\Enums\Content;

use App\Traits\AdvanceEnum;

/**
 * Allowlist level a rich text field is sanitized against.
 *
 * The tags each level permits live in `config/rich-text.php`; the level is
 * declared at the write DTO property it governs, never sent by a client.
 */
enum RichTextLevelEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case INLINE  = 'inline';
    case COMPACT = 'compact';
    case ARTICLE = 'article';

}
