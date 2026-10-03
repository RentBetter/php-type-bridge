<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Http\Include\Fixtures;

use PTGS\TypeBridge\Enum\ApiEnum;

/**
 * Sent whole as `{id, name, theme}`, or as its id on request.
 */
enum CheckStatus: string implements ApiEnum
{
    case Ok = 'ok';
    case Warning = 'warning';

    public function id(): string
    {
        return strtoupper($this->name);
    }

    /**
     * @return array{id: string, name: string, theme: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id(),
            'name' => $this->name,
            'theme' => match ($this) {
                self::Ok => 'green',
                self::Warning => 'amber',
            },
        ];
    }
}
