<?php

declare(strict_types=1);

namespace Bolt\Event;

use Bolt\Entity\Content;
use Symfony\Contracts\EventDispatcher\Event;

class ContentEvent extends Event
{
    public const string PRE_SAVE = 'bolt.pre_save';
    public const string POST_SAVE = 'bolt.post_save';
    public const string ON_EDIT = 'bolt.pre_edit';
    public const string ON_PREVIEW = 'bolt.pre_edit';
    public const string ON_DUPLICATE = 'bolt.on_duplicate';
    public const string PRE_STATUS_CHANGE = 'bolt.pre_status_change';
    public const string POST_STATUS_CHANGE = 'bolt.post_status_change';
    public const string PRE_DELETE = 'bolt.pre_delete';
    public const string POST_DELETE = 'bolt.post_delete';

    public function __construct(private readonly Content $content) {}

    public function getContent(): Content
    {
        return $this->content;
    }
}
