<?php

declare(strict_types=1);

namespace Bolt\Widget\Injector;

use ReflectionClass;

/**
 * This class categorizes all possible automatic widget locations.
 */
class Target
{
    // works even without valid html
    public const string BEFORE_HTML = 'beforecontent';
    public const string AFTER_HTML = 'aftercontent';
    public const string BEFORE_CONTENT = 'beforecontent';
    public const string AFTER_CONTENT = 'aftercontent';

    // unpredictable
    public const string BEFORE_CSS = 'beforecss';
    public const string AFTER_CSS = 'aftercss';
    public const string BEFORE_JS = 'beforejs';
    public const string AFTER_JS = 'afterjs';
    public const string AFTER_META = 'aftermeta';

    // main structure
    public const string START_OF_HEAD = 'startofhead';
    public const string END_OF_HEAD = 'endofhead';
    public const string START_OF_BODY = 'startofbody';
    public const string END_OF_BODY = 'endofbody';
    public const string END_OF_HTML = 'endofhtml';

    // substructure
    public const string BEFORE_HEAD_META = 'beforeheadmeta';
    public const string AFTER_HEAD_META = 'afterheadmeta';

    public const string BEFORE_HEAD_CSS = 'beforeheadcss';
    public const string AFTER_HEAD_CSS = 'afterheadcss';

    public const string BEFORE_HEAD_JS = 'beforeheadjs';
    public const string AFTER_HEAD_JS = 'afterheadjs';

    public const string BEFORE_BODY_CSS = 'beforebodycss';
    public const string AFTER_BODY_CSS = 'afterbodycss';

    public const string BEFORE_BODY_JS = 'beforebodyjs';
    public const string AFTER_BODY_JS = 'afterbodyjs';

    // this one goes nowhere in html
    public const string NOWHERE = 'nowhere';

    public function listAll(): array
    {
        $reflection = new ReflectionClass($this);

        return $reflection->getConstants();
    }
}
