<?php

declare(strict_types=1);

arch('every source file declares strict types')
    ->expect('Beziworld\ISPConfig')
    ->toUseStrictTypes();

arch('no debugging statements are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('data objects are immutable')
    ->expect('Beziworld\ISPConfig\Data')
    ->toBeReadonly();

arch('exceptions share the package base exception')
    ->expect('Beziworld\ISPConfig\Exceptions')
    ->toExtend('Beziworld\ISPConfig\Exceptions\ISPConfigException')
    ->ignoring('Beziworld\ISPConfig\Exceptions\ISPConfigException');
