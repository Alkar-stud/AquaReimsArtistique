<?php
namespace app\Services\Log\Handler;

interface LogHandlerInterface
{
    public function handle(array $record): void;
}
