<?php

declare(strict_types=1);

use App\Enums\System\CacheKey;
use App\Enums\System\CacheTag;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/**
 * The cache module owns every read and write of cached values. Application code
 * outside it goes through the {@see App\Contracts\Cache\CacheStore} gateway, so a key,
 * its lifetime and its invalidation group cannot be re-declared ad hoc.
 *
 * Locks are deliberately not wrapped: `Cache::lock()` is coordination rather than
 * storage and stays at the call site.
 */
const CACHE_GATEWAY_MODULE_PATHS = ['Contracts/Cache', 'Services/Cache'];

/**
 * Every application PHP file outside the cache module.
 *
 * @return list<string>
 */
function cacheGatewayScannedFiles(): array
{
    $appRoot  = dirname(__DIR__, 2).'/app';
    $files    = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($appRoot, FilesystemIterator::SKIP_DOTS),
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $relative = str_replace($appRoot.'/', '', $file->getPathname());

        foreach (CACHE_GATEWAY_MODULE_PATHS as $modulePath) {
            if (str_starts_with($relative, $modulePath.'/')) {
                continue 2;
            }
        }

        $files[] = $file->getPathname();
    }

    sort($files);

    return $files;
}

/**
 * Parse a PHP source into an AST with every name resolved to its fully qualified form,
 * so `use Illuminate\Support\Facades\Cache;` and the inline FQN are the same node.
 *
 * @return list<Node\Stmt>
 */
function cacheGatewayAst(string $code): array
{
    $statements = (new ParserFactory())->createForNewestSupportedVersion()->parse($code) ?? [];

    return (new NodeTraverser(new NameResolver()))->traverse($statements);
}

/**
 * Every function, method and static call in the source.
 *
 * @return list<CallLike>
 */
function cacheGatewayCalls(string $code): array
{
    /** @var list<CallLike> $calls */
    $calls = (new NodeFinder())->find(
        cacheGatewayAst($code),
        static fn (Node $node): bool => $node instanceof CallLike,
    );

    return $calls;
}

function cacheGatewayCallName(CallLike $call): ?string
{
    if ($call instanceof FuncCall || $call instanceof StaticCall || $call instanceof MethodCall || $call instanceof NullsafeMethodCall) {
        return $call->name instanceof Identifier || $call->name instanceof Name
            ? $call->name->toString()
            : null;
    }

    return null;
}

/**
 * Cache storage reached for outside the cache module: the cache facade, its helper,
 * or a package cache facade. `Cache::lock()` is the one allowed call.
 *
 * @return list<string>
 */
function cacheGatewayStorageViolations(string $code): array
{
    $violations = [];

    foreach (cacheGatewayCalls($code) as $call) {
        if ($call instanceof FuncCall && $call->name instanceof Name && mb_strtolower($call->name->getLast()) === 'cache') {
            $violations[] = sprintf('line %d: %s()', $call->getStartLine(), $call->name->toString());

            continue;
        }

        if (! $call instanceof StaticCall || ! $call->class instanceof Name) {
            continue;
        }

        $short  = $call->class->getLast();
        $method = cacheGatewayCallName($call);

        if (! in_array($short, ['Cache', 'SmartCache'], true)) {
            continue;
        }

        if ($short === 'Cache' && $method === 'lock') {
            continue;
        }

        $violations[] = sprintf('line %d: %s::%s()', $call->getStartLine(), $call->class->toString(), (string) $method);
    }

    return $violations;
}

/**
 * Tag and key case names that literal calls in the source clear: `CacheTag::X` passed
 * to an `invalidate(...)` call, and `CacheKey::Y` passed to a `forget(...)` call.
 *
 * @return array{tags: list<string>, keys: list<string>}
 */
function cacheGatewayLiteralWriters(string $code): array
{
    $tags = [];
    $keys = [];

    foreach (cacheGatewayCalls($code) as $call) {
        $method = cacheGatewayCallName($call);

        if ($method !== 'invalidate' && $method !== 'forget') {
            continue;
        }

        foreach ($call->getArgs() as $argument) {
            $value = $argument->value;

            if (! $value instanceof ClassConstFetch || ! $value->class instanceof Name || ! $value->name instanceof Identifier) {
                continue;
            }

            $enum = $value->class->getLast();
            $case = $value->name->toString();

            if ($method === 'invalidate' && $enum === 'CacheTag') {
                $tags[] = $case;
            }

            if ($method === 'forget' && $enum === 'CacheKey') {
                $keys[] = $case;
            }
        }
    }

    return ['tags' => $tags, 'keys' => $keys];
}

/**
 * Cache tags with no writer clearing them.
 *
 * @param  array<string, list<string>>  $cleared
 * @return list<string>
 */
function cacheGatewayUncoveredTags(array $cleared): array
{
    return array_values(array_filter(
        array_map(static fn (CacheTag $tag): string => $tag->value, CacheTag::cases()),
        static fn (string $value): bool => ($cleared[$value] ?? []) === [],
    ));
}

/**
 * Storage violations across the scanned tree, as path-prefixed messages.
 *
 * @return list<string>
 */
function cacheGatewayTreeViolations(): array
{
    $violations = [];

    foreach (cacheGatewayScannedFiles() as $file) {
        foreach (cacheGatewayStorageViolations((string) file_get_contents($file)) as $violation) {
            $violations[] = str_replace(dirname(__DIR__, 2).'/', '', $file).': '.$violation;
        }
    }

    return $violations;
}

/**
 * Tag value => files whose literal writers clear it. A writer is an
 * `invalidate(CacheTag::X)` call or a `forget(CacheKey::Y)` call whose key belongs to `X`.
 *
 * @return array<string, list<string>>
 */
function cacheGatewayClearedTags(): array
{
    $cleared = [];

    foreach (cacheGatewayScannedFiles() as $file) {
        $writers = cacheGatewayLiteralWriters((string) file_get_contents($file));

        foreach ($writers['tags'] as $case) {
            if (defined(CacheTag::class.'::'.$case)) {
                $cleared[constant(CacheTag::class.'::'.$case)->value][] = $file;
            }
        }

        foreach ($writers['keys'] as $case) {
            if (defined(CacheKey::class.'::'.$case)) {
                $cleared[constant(CacheKey::class.'::'.$case)->group()->value][] = $file;
            }
        }
    }

    return $cleared;
}

it('allows Cache::lock() outside the cache module', function (): void {
    expect(cacheGatewayStorageViolations('<?php Cache::lock("name", 5);'))->toBe([])
        ->and(cacheGatewayStorageViolations('<?php Illuminate\Support\Facades\Cache::lock("name", 5);'))->toBe([]);
});

it('flags every other cache facade, helper and package facade call', function (): void {
    expect(cacheGatewayStorageViolations('<?php use Illuminate\Support\Facades\Cache; Cache::get("key");'))
        ->toBe(['line 1: Illuminate\Support\Facades\Cache::get()'])
        ->and(cacheGatewayStorageViolations('<?php Illuminate\Support\Facades\Cache::put("k", 1, 5);'))
        ->toBe(['line 1: Illuminate\Support\Facades\Cache::put()'])
        ->and(cacheGatewayStorageViolations('<?php cache("key");'))->toBe(['line 1: cache()'])
        ->and(cacheGatewayStorageViolations('<?php SmartCache::forget("key");'))->toBe(['line 1: SmartCache::forget()']);
});

it('ignores cache-named methods and functions that are not the cache facade', function (): void {
    expect(cacheGatewayStorageViolations('<?php $model->cache("key"); $other?->cache("key"); Foo::cache("key"); function cache($k) {}'))
        ->toBe([]);
});

it('keeps ad-hoc cache storage out of application code', function (): void {
    expect(cacheGatewayTreeViolations())->toBe([]);
});

it('finds the literal tags a writer invalidates and the keys it forgets', function (): void {
    $writers = cacheGatewayLiteralWriters(
        '<?php $cache->invalidate(CacheTag::Search); $other->invalidate(CacheTag::Catalog, CacheTag::Search);'
        .' $cache->forget(CacheKey::Settings); $cache->get(CacheKey::Slider);'
    );

    expect($writers['tags'])->toBe(['Search', 'Catalog', 'Search'])
        ->and($writers['keys'])->toBe(['Settings']);
});

it('reports every tag as uncovered when no writer clears it', function (): void {
    expect(cacheGatewayUncoveredTags([]))->toBe(array_map(
        static fn (CacheTag $tag): string => $tag->value,
        CacheTag::cases(),
    ));
});

it('has at least one writer clearing every cache tag', function (): void {
    expect(cacheGatewayUncoveredTags(cacheGatewayClearedTags()))->toBe([]);
});
