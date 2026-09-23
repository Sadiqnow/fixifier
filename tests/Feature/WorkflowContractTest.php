<?php
namespace Tests\Feature;
use PHPUnit\Framework\TestCase;
final class WorkflowContractTest extends TestCase { public function test_required_workflow_states_are_documented():void{$readme=file_get_contents(__DIR__.'/../../README.md');foreach(['requested','quoted','confirmed','in_progress','evidence_submitted','completed','disputed'] as $state)$this->assertStringContainsString($state,$readme);} }
