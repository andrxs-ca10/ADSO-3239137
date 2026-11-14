<?php

namespace App\Support;

use DateTimeInterface;

enum LoanStatus: string
{
    case Open = 'open';
    case Overdue = 'overdue';
    case Closed = 'closed';

    /**
     * @param iterable<array{due_at: DateTimeInterface, returned_at: DateTimeInterface|null}> $items
     */
    public static function fromItems(iterable $items, DateTimeInterface $now): self
    {
        $status = self::Closed;

        foreach ($items as $item) {
            if ($item['returned_at'] !== null) {
                continue;
            }

            if ($item['due_at'] < $now) {
                return self::Overdue;
            }

            $status = self::Open;
        }

        return $status;
    }
}