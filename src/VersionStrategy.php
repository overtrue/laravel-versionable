<?php

namespace Overtrue\LaravelVersionable;

enum VersionStrategy
{
    case DIFF; // changed attributes in $versionable
    case SNAPSHOT; // all attributes in $versionable
    case ALL; // all attributes of the model
}
