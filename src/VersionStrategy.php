<?php

namespace Overtrue\LaravelVersionable;

enum VersionStrategy: string
{
    // save changed attributes in $versionable
    case DIFF = 'DIFF';

    // save all attributes in $versionable
    case SNAPSHOT = 'SNAPSHOT';
}
