<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\File\FileService;

$dsn = getenv('FILE_LIST_TEST_DSN');
if (!is_string($dsn) || !preg_match('~^mysql:unix_socket=/tmp/php-core-file-list\.[^/]+/mysql\.sock;dbname=file_list_test;charset=utf8mb4$~D', $dsn)) {
    throw new RuntimeException('Disposable file_list_test database required');
}
$server = new PDO(explode(';dbname=', $dsn)[0], 'root', '');
$server->exec('CREATE DATABASE file_list_test CHARACTER SET utf8mb4');
$pdo = new PDO($dsn, 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$pdo->exec(file_get_contents(dirname(__DIR__) . '/migrations/schema.sql'));
$databaseClass = new ReflectionClass(Database::class);
$db = $databaseClass->newInstanceWithoutConstructor();
$databaseClass->getProperty('_pdo')->setValue($db, $pdo);

final class FileListTestAuth extends Auth
{
    public function __construct(private readonly bool $admin, private readonly int $owner) {}
    public function require(): void {}
    public function hasRole(string $role): bool { return $this->admin && $role === 'admin'; }
    public function id(): ?int { return $this->owner; }
}

function names(array $result): array
{
    return array_column($result['data'], 'name');
}
function checkList(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL ' . $label);
    }
    echo 'PASS ' . $label . PHP_EOL;
}
$rows = [
    ['zoo', 11, 'Alpha.pdf', 'pdf', 100, 0, '2026-01-01 00:00:00'],
    ['zoo', 11, 'Budget%.txt', 'txt', 20, 0, '2026-01-02 00:00:00'],
    ['zoo', 12, 'Beta.txt', 'txt', 40, 0, '2026-01-03 00:00:00'],
    ['zoo', 12, '123.txt', 'txt', 30, 0, '2026-01-01 12:00:00'],
    ['zoo', 11, 'Archive.pdf', 'pdf', 5, 1, '2026-01-04 00:00:00'],
    ['other', 11, 'Foreign.pdf', 'pdf', 80, 0, '2026-01-05 00:00:00'],
];
foreach ($rows as [$tenant, $owner, $name, $type, $size, $deleted, $created]) {
    $db->insert('file', [
        'franchise_code' => $tenant, 'user_id' => $owner, 'name' => $name,
        'type' => $type, 'mime_type' => 'application/octet-stream',
        'path' => 'files/' . $tenant . '/' . $name, 'size' => $size,
        'deleted' => $deleted, 'created_at' => $created,
    ]);
}
$admin = new FileService($db, 'zoo', new FileListTestAuth(true, 11));
$user = new FileService($db, 'zoo', new FileListTestAuth(false, 11));
checkList(names($admin->list(1, 20, 'created_desc', '', null)) === ['Beta.txt', 'Budget%.txt', '123.txt', 'Alpha.pdf'], 'legacy created_desc sorts existing files');
checkList(names($admin->list(1, 20, 'name_desc', '', null)) === ['Budget%.txt', 'Beta.txt', 'Alpha.pdf', '123.txt'], 'legacy name_desc sorts existing files');
checkList(names($admin->list(1, 20, 'size', '', null)) === ['Budget%.txt', '123.txt', 'Beta.txt', 'Alpha.pdf'], 'legacy size sort works');
checkList(names($admin->list(1, 20, '[{"name":1}]', '', null)) === ['123.txt', 'Alpha.pdf', 'Beta.txt', 'Budget%.txt'], 'JSON sort works');
checkList(names($admin->list(1, 20, '[{"nonexistent":1}]', '', null)) === ['Beta.txt', 'Budget%.txt', '123.txt', 'Alpha.pdf'], 'unknown sort falls back safely');
checkList(names($admin->list(1, 20, '', 'Alpha', null)) === ['Alpha.pdf'], 'legacy plain-text q searches file name');
checkList(names($admin->list(1, 20, '', '123', null)) === ['123.txt'], 'numeric plain-text q remains searchable');
checkList(names($admin->list(1, 20, '', '{"search":"pdf"}', null)) === ['Alpha.pdf'], 'legacy search field searches name or type');
checkList(names($admin->list(1, 20, '', '{"name":{"$regex":"Budget"}}', null)) === ['Budget%.txt'], 'JSON q searches name');
checkList(names($admin->list(1, 20, '', '{"name":{"$regex":"%"}}', null)) === ['Budget%.txt'], 'percent is a literal in JSON q');
checkList(names($admin->list(1, 20, '', '{"deleted":{"value":0}}', null)) === ['Beta.txt', 'Budget%.txt', '123.txt', 'Alpha.pdf'], 'deleted zero spec keeps active files');
checkList(names($admin->list(1, 20, '', '{"deleted":{"$eq":1}}', null)) === ['Archive.pdf'], 'deleted one spec selects archived files for admin');
checkList(names($user->list(1, 20, '', '{"deleted":{"value":1}}', null)) === ['Budget%.txt', 'Alpha.pdf'], 'non-admin remains owner scoped and cannot view deleted files');
checkList(names($admin->list(1, 20, '', '{"name":{"$regex":"Foreign"}}', null)) === [], 'tenant scope holds with JSON q');

// The same query contract is used by Zoo, FAnn and Zajeci product lists.
$top = $db->insert('category', ['franchise_code' => 'zajeci', 'syscode' => 'top', 'name' => 'Top wines', 'published' => 1]);
$hiddenCategory = $db->insert('category', ['franchise_code' => 'zajeci', 'syscode' => 'hidden', 'name' => 'Hidden', 'published' => 0]);
$topWine = $db->insert('product', ['franchise_code' => 'zajeci', 'sku' => 'TOP-1', 'name' => 'Top Wine', 'published' => 1]);
$draftWine = $db->insert('product', ['franchise_code' => 'zajeci', 'sku' => 'TOP-2', 'name' => 'Draft Wine', 'published' => 0]);
$hiddenWine = $db->insert('product', ['franchise_code' => 'zajeci', 'sku' => 'HIDDEN-1', 'name' => 'Hidden Wine', 'published' => 1]);
$foreignWine = $db->insert('product', ['franchise_code' => 'other', 'sku' => 'TOP-1', 'name' => 'Foreign Wine', 'published' => 1]);
$foreignTop = $db->insert('category', ['franchise_code' => 'other', 'syscode' => 'top', 'name' => 'Foreign top', 'published' => 1]);
foreach ([[$topWine, $top], [$draftWine, $top], [$hiddenWine, $hiddenCategory], [$foreignWine, $foreignTop]] as [$productId, $categoryId]) {
    $db->insert('product_category', ['product_id' => $productId, 'category_id' => $categoryId]);
}
$publicProducts = new App\Modules\Product\ProductService($db, 'zajeci', new FileListTestAuth(false, 11));
$adminProducts = new App\Modules\Product\ProductService($db, 'zajeci', new FileListTestAuth(true, 11));
$topFilter = '{"category.syscode":{"value":"top"}}';
checkList(names($publicProducts->list(1, 20, '', $topFilter)) === ['Top Wine'], 'Zajeci public top-wine filter keeps tenant and publication scope');
$adminNames = names($adminProducts->list(1, 20, '', $topFilter));
sort($adminNames);
checkList($adminNames === ['Draft Wine', 'Top Wine'], 'Zoo and FAnn admin category filter still resolves relation');
