<?php

namespace Tests\Unit;

use App\Support\LoanStatus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LoanStatusTest extends TestCase
{
    #[DataProvider('statusCases')]
    public function test_it_calculates_the_status_from_loan_items(
        array $dates,
        LoanStatus $expected,
    ): void {
        $items = array_map(
            static fn (array $item): array => [
                'due_at' => new DateTimeImmutable($item[0]),
                'returned_at' => $item[1] === null ? null : new DateTimeImmutable($item[1]),
            ],
            $dates,
        );

        self::assertSame(
            $expected,
            LoanStatus::fromItems($items, new DateTimeImmutable('2026-11-14T12:00:00Z')),
        );
    }

    public static function statusCases(): array
    {
        return [
            'pendiente justo en el vencimiento' => [
                [['2026-11-14T12:00:00Z', null]],
                LoanStatus::Open,
            ],
            'una línea vencida entre varias abiertas' => [
                [
                    ['2026-11-15T12:00:00Z', null],
                    ['2026-11-13T12:00:00Z', null],
                ],
                LoanStatus::Overdue,
            ],
            'línea devuelta tarde y otra pendiente a tiempo' => [
                [
                    ['2026-11-12T12:00:00Z', '2026-11-13T12:00:00Z'],
                    ['2026-11-15T12:00:00Z', null],
                ],
                LoanStatus::Open,
            ],
            'todas las líneas devueltas' => [
                [['2026-11-12T12:00:00Z', '2026-11-13T12:00:00Z']],
                LoanStatus::Closed,
            ],
        ];
    }
}