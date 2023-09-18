<?php

namespace vmybook\turbopages;

use yii\db\Query;

interface FeedItemInterface
{
    public static function getItems(int $offset, int $limit): Query;
    
    public function getId(): int;
    public function getTitle(): string;
    public function getDescription(): string;
    public function getLink(): string;
    public function getContent(): string;
    public function getPubDate(): string;
}
