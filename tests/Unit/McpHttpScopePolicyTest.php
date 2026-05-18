<?php

declare(strict_types=1);

use Bnomei\KirbyMcp\Mcp\Http\HttpAuthScopes;
use Bnomei\KirbyMcp\Mcp\Http\HttpScopePolicy;

it('classifies JSON-RPC discovery and resource operations as read scope without hiding surface', function (): void {
    $policy = new HttpScopePolicy();

    expect($policy->requiredScopes('initialize'))->toBe([HttpAuthScopes::READ])
        ->and($policy->requiredScopes('tools/list'))->toBe([HttpAuthScopes::READ])
        ->and($policy->requiredScopes('resources/list'))->toBe([HttpAuthScopes::READ])
        ->and($policy->requiredScopes('resources/read', ['uri' => 'kirby://kb']))->toBe([HttpAuthScopes::READ])
        ->and($policy->requiredScopes('logging/setLevel'))->toBe([HttpAuthScopes::ADMIN]);
});

it('classifies actual sensitive tools into runtime write execute and admin scopes', function (): void {
    $policy = new HttpScopePolicy();

    expect($policy->requiredScopes('tools/call', ['name' => 'kirby_info']))->toBe([HttpAuthScopes::READ])
        ->and($policy->requiredScopes('tools/call', ['name' => 'kirby_runtime_status']))->toBe([HttpAuthScopes::RUNTIME])
        ->and($policy->requiredScopes('tools/call', ['name' => 'kirby_render_page']))->toBe([HttpAuthScopes::RUNTIME])
        ->and($policy->requiredScopes('tools/call', ['name' => 'kirby_read_page_content']))->toBe([HttpAuthScopes::RUNTIME])
        ->and($policy->requiredScopes('tools/call', ['name' => 'kirby_routes_index']))->toBe([HttpAuthScopes::RUNTIME])
        ->and($policy->requiredScopes('tools/call', ['name' => 'kirby_blueprints_loaded']))->toBe([HttpAuthScopes::RUNTIME])
        ->and($policy->requiredScopes('tools/call', ['name' => 'kirby_update_page_content']))->toBe([HttpAuthScopes::WRITE])
        ->and($policy->requiredScopes('tools/call', ['name' => 'kirby_generate_ide_helpers']))->toBe([HttpAuthScopes::WRITE])
        ->and($policy->requiredScopes('tools/call', ['name' => 'kirby_run_cli_command']))->toBe([HttpAuthScopes::EXECUTE])
        ->and($policy->requiredScopes('tools/call', ['name' => 'kirby_eval']))->toBe([HttpAuthScopes::EXECUTE])
        ->and($policy->requiredScopes('tools/call', ['name' => 'kirby_query_dot']))->toBe([HttpAuthScopes::EXECUTE])
        ->and($policy->requiredScopes('tools/call', ['name' => 'kirby_cache_clear']))->toBe([HttpAuthScopes::ADMIN])
        ->and($policy->requiredScopes('tools/call', ['name' => 'kirby_runtime_install']))->toBe([HttpAuthScopes::ADMIN]);
});

it('classifies HTTP GET and OPTIONS as read and DELETE as admin session operations', function (): void {
    $policy = new HttpScopePolicy();

    expect($policy->methodScopes('GET'))->toBe([HttpAuthScopes::READ])
        ->and($policy->methodScopes('OPTIONS'))->toBe([HttpAuthScopes::READ])
        ->and($policy->methodScopes('DELETE'))->toBe([HttpAuthScopes::ADMIN]);
});
