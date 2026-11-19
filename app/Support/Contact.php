<?php

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * How someone can be reached: an email address or a phone number, typed into
 * the one "email or phone" field that sign-in and checkout share.
 *
 * Always normalised, so the same person typing their contact two ways still
 * finds the same account: an address is lowercased (`fortify.lowercase_usernames`
 * is on, so accounts are stored that way), a number becomes E.164
 * (`+212612345678`) whether it was typed `06 12 34 56 78`, `+212 6…` or
 * `00212 6…`.
 *
 * A number without a country code is read as Moroccan. Most shoppers are, and
 * they write their number the national way, with the leading 0.
 */
final class Contact
{
    public const EMAIL = 'email';

    public const PHONE = 'phone';

    /** The country a number written without its country code belongs to. */
    public const DEFAULT_REGION = 'MA';

    private function __construct(
        public readonly string $channel,
        public readonly string $value,
    ) {}

    /**
     * What a visitor typed, or null when it is neither a valid address nor a
     * number that can exist.
     *
     * "Can exist" is libphonenumber's `isValidNumber`: the right length and a
     * real prefix for the country. It cannot know the number is *theirs* —
     * only the code sent to it can — but it stops a digit too many or too few
     * before anything is sent.
     */
    public static function parse(?string $raw): ?self
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        if (str_contains($raw, '@')) {
            $email = mb_strtolower($raw);

            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false
                ? new self(self::EMAIL, $email)
                : null;
        }

        // Letters and no @: a mistyped address, not a number.
        if (preg_match('/\p{L}/u', $raw)) {
            return null;
        }

        $number = self::parseNumber($raw);

        return $number
            ? new self(self::PHONE, PhoneNumberUtil::getInstance()->format($number, PhoneNumberFormat::E164))
            : null;
    }

    /**
     * Whether the raw input looks like an attempt at a number rather than an
     * address — picks which error message a failed parse gets.
     */
    public static function looksLikePhone(?string $raw): bool
    {
        $raw = trim((string) $raw);

        return $raw !== '' && ! str_contains($raw, '@') && ! preg_match('/\p{L}/u', $raw);
    }

    /** A number already stored in E.164, as written by parse(). */
    public static function phone(string $e164): self
    {
        return new self(self::PHONE, $e164);
    }

    public static function email(string $email): self
    {
        return new self(self::EMAIL, mb_strtolower($email));
    }

    public function isEmail(): bool
    {
        return $this->channel === self::EMAIL;
    }

    public function isPhone(): bool
    {
        return $this->channel === self::PHONE;
    }

    /**
     * Readable: `+212 6 12 34 56 78` for a number. Shown back to the person
     * who typed it, so they can spot a wrong digit before relying on it.
     */
    public function display(): string
    {
        if ($this->isEmail()) {
            return $this->value;
        }

        $number = self::parseNumber($this->value);

        return $number
            ? PhoneNumberUtil::getInstance()->format($number, PhoneNumberFormat::INTERNATIONAL)
            : $this->value;
    }

    /**
     * `ac•••@example.com` / `+212 •••••••78` — enough to recognise, for screens
     * that may be read over a shoulder or resumed days later on a shared
     * machine.
     */
    public function masked(): string
    {
        if ($this->isEmail()) {
            [$local, $domain] = array_pad(explode('@', $this->value, 2), 2, '');

            $visible = mb_substr($local, 0, min(2, max(1, mb_strlen($local) - 1)));

            return $visible.str_repeat('•', 3).'@'.$domain;
        }

        $number = self::parseNumber($this->value);

        if (! $number) {
            return str_repeat('•', 6).substr($this->value, -2);
        }

        $national = (string) $number->getNationalNumber();

        return '+'.$number->getCountryCode().' '
            .str_repeat('•', max(0, strlen($national) - 2))
            .substr($national, -2);
    }

    /** The number's country (`MA`, `FR`…), or null for an address. */
    public function region(): ?string
    {
        if ($this->isEmail()) {
            return null;
        }

        $number = self::parseNumber($this->value);

        return $number ? PhoneNumberUtil::getInstance()->getRegionCodeForNumber($number) : null;
    }

    private static function parseNumber(string $raw): ?PhoneNumber
    {
        $util = PhoneNumberUtil::getInstance();

        try {
            $number = $util->parse($raw, self::DEFAULT_REGION);
        } catch (NumberParseException) {
            return null;
        }

        return $util->isValidNumber($number) ? $number : null;
    }
}
