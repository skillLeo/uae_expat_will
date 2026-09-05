<?php

namespace App\Domain\Settings;

/**
 * How a table-shaped setting is edited in the admin.
 *
 * A JSON setting renders as a raw textarea by default, which is fine for a
 * developer and useless for anyone else: the authority-charge table is a
 * legitimate business decision Summit must be able to change, and it was
 * sitting behind hand-typed JSON where a single missing comma refused the
 * whole save. So Summit could not change their own prices, asked us to change
 * them, and the request travelled through screenshots and WhatsApp — which is
 * how a number ended up being argued about for four days.
 *
 * A key with a schema here gets proper labelled fields with add, remove and
 * reorder. Anything without one keeps the JSON textarea.
 *
 * `amount` is deliberately free text rather than a number. The DIFC row reads
 * "Varies by Will type" and the Dubai row carries a "≈", because those are
 * honest descriptions of what the authority actually charges. Forcing a
 * numeric field would make the truth unrepresentable.
 */
class RowSchemas
{
    /** @var array<string, array{add_label: string, empty: string, columns: list<array<string, string>>}> */
    private const SCHEMAS = [
        'commercial.authority_fees' => [
            'add_label' => 'Add a route',
            'empty' => 'No authority charges are listed. The table is hidden from the Pricing page until you add one.',
            'columns' => [
                [
                    'key' => 'route',
                    'label' => 'Route',
                    'type' => 'text',
                    'placeholder' => 'ADJD Civil Will',
                    'width' => '1fr',
                ],
                [
                    'key' => 'amount',
                    'label' => 'Charge shown',
                    'type' => 'text',
                    'placeholder' => 'AED 950.00',
                    'help' => 'Free text. Write a figure, or words such as "Varies by Will type".',
                    'width' => '1fr',
                ],
                [
                    'key' => 'note',
                    'label' => 'Note beside it',
                    'type' => 'textarea',
                    'placeholder' => 'For one regular Will, subject to the authority’s current fee schedule',
                    'width' => '1.6fr',
                ],
            ],
        ],
    ];

    /** @return array<string, mixed>|null */
    public static function for(string $key): ?array
    {
        return self::SCHEMAS[$key] ?? null;
    }

    /**
     * A blank row, so "add" produces every column rather than an empty object
     * the table would then render as a row of nothing.
     *
     * @return array<string, string>
     */
    public static function blankRow(string $key): array
    {
        $schema = self::for($key);

        if ($schema === null) {
            return [];
        }

        return array_fill_keys(array_column($schema['columns'], 'key'), '');
    }
}
