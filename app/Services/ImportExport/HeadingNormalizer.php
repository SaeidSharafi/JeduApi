<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

use App\Helpers\PersianDigitHelper;

/**
 * Normalizes spreadsheet headings so equivalent English and Persian spellings
 * resolve to the same column.
 *
 * Persian/Arabic letter variants, the zero width non-joiner, zero width and
 * bidi control characters, diacritics, digits and punctuation are all folded
 * before comparison.
 */
final class HeadingNormalizer
{
    /** Characters that only carry text direction or joining hints. */
    private const array INVISIBLE_CHARACTERS = [
        "\u{200D}", "\u{200E}", "\u{200F}",
        "\u{202A}", "\u{202B}", "\u{202C}", "\u{202D}", "\u{202E}",
        "\u{2060}", "\u{FEFF}", "\u{00AD}",
    ];

    /** Arabic diacritics (harakat) and the tatweel elongation character. */
    private const array DIACRITICS = [
        "\u{064B}", "\u{064C}", "\u{064D}", "\u{064E}", "\u{064F}", "\u{0650}",
        "\u{0651}", "\u{0652}", "\u{0653}", "\u{0654}", "\u{0655}", "\u{0656}",
        "\u{0657}", "\u{0658}", "\u{0670}", "\u{0640}",
    ];

    /** @var array<string, string> */
    private const array CHARACTER_VARIANTS = [
        "\u{064A}" => "\u{06CC}", // Arabic yeh       -> Persian yeh
        "\u{0649}" => "\u{06CC}", // Alef maksura     -> Persian yeh
        "\u{06AA}" => "\u{06A9}", // Swash kaf        -> Persian kaf
        "\u{0643}" => "\u{06A9}", // Arabic kaf       -> Persian kaf
        "\u{0622}" => "\u{0627}", // Alef with madda  -> Alef
        "\u{0623}" => "\u{0627}", // Alef with hamza  -> Alef
        "\u{0625}" => "\u{0627}", // Alef with hamza  -> Alef
        "\u{0671}" => "\u{0627}", // Alef wasla       -> Alef
    ];

    public function normalize(?string $heading): string
    {
        $heading = (string) $heading;

        // The Persian non-joiner separates words; keep it a word boundary.
        $heading = str_replace("\u{200C}", ' ', $heading);
        $heading = str_replace(self::INVISIBLE_CHARACTERS, '', $heading);
        $heading = str_replace(self::DIACRITICS, '', $heading);
        $heading = strtr($heading, self::CHARACTER_VARIANTS);
        $heading = PersianDigitHelper::toAscii($heading);

        $heading = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $heading);

        return (string) preg_replace('/\s+/u', ' ', mb_strtolower(mb_trim($heading)));
    }
}
