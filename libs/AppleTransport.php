<?php

declare(strict_types=1);

namespace SymconiCloud;

interface AppleTransport
{
    /** @return array{status:int,headers:array<string,string>,body:array} */
    public function request(string $method, string $url, array $headers, ?array $body): array;

    /** @return list<string> Domain/path scoped Netscape cookie records. */
    public function cookies(): array;
}
