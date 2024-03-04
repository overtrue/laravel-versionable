<?php

namespace Overtrue\LaravelVersionable;

enum VersionStrategy
{
    case DIFF; // save changed attributes in $versionable
    case SNAPSHOT; // save all attributes in $versionable
}
