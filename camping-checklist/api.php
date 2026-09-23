<?php
// Camping checklist sync endpoint.
// GET  api.php            -> returns current state (seeds it on first run)
// POST api.php {action..} -> applies one change, returns new state
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

const DATA_DIR  = __DIR__ . '/data';
const DATA_FILE = DATA_DIR . '/state.json';

function fail(int $code, string $msg): void
{
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

function clean($value, int $max = 120): string
{
    $v = trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
}

function newId(string $prefix): string
{
    return $prefix . bin2hex(random_bytes(4));
}

function seed(): array
{
    $raw = [
        'Camping Gear' => [
            'Tent + vestibule', 'Tarp/footprint', 'Rug', 'Screen tent', 'Cots',
            'Blankets under cots', 'Sleeping bags', 'Pillows', 'Water container',
            'Power cords', 'Lanterns', 'Headlamps', 'Chuckwagon', 'Camp chairs',
            'Fire starters', 'Firewood (buy local)', 'Bug spray', 'Trash bags', 'First aid kit',
        ],
        'Astro' => [
            'E-collar', 'E-collar remote', 'E-collar charger', 'Allergy meds', 'Ear meds',
            'Styptic powder', 'Food', 'Bowls', 'Blanket', 'Cot', 'Tie-out', 'Ball',
            'Chew toy', 'Leash', 'Herm Sprenger', 'Poop bags', 'Paw towel',
            'Care note for Saturday sitter',
        ],
        'Josh' => [
            'Cozy camp set (joggers + hoodie)', '2 jeans', '2 shorts', '4 T-shirts',
            '1 long sleeve', '2 hoodies', 'Ball cap', 'Beanie', 'Underwear', 'Socks',
            'Warm sleep socks', 'Camp shoes', 'Boots', 'Medicine', 'Sunglasses',
            'Toiletries', 'Towel', 'Shower shoes', 'Sunscreen', 'Cash for fair',
            'Fishing pole', 'Tackle box', 'Fishing license',
        ],
        'Ren Faire (Saturday)' => [
            'Great kilt', 'Ren shirt', 'Wool socks', 'Brown boots',
            '2 extra tartans for friends', 'Change-back outfit',
        ],
        'Food' => [
            'Thu dinner: pasta salad', 'Fri breakfast: eggs (2 dozen)', 'Fri breakfast: bread',
            'Fri breakfast: bagels + cream cheese', 'Fri breakfast: butter',
            'Instant oatmeal (2 boxes, Fri + Sun)', 'Fri lunch: tuna + mayo',
            'Fri lunch: deli meat + cheese', 'Fri lunch: bread',
            'Fri lunch: lettuce, tomato, condiments', 'Fri lunch: chips', 'Fri lunch: fruit/veg',
            'Sat dinner: hotdogs + buns', 'Sat dinner: ketchup, mustard, relish',
            'Coffee + creamer', 'Snacks', 'Drugs', 'Sodas', 'Beer/wine', 'Ice',
        ],
    ];

    $sections = [];
    $s = 0;
    foreach ($raw as $name => $items) {
        $s++;
        $list = [];
        foreach ($items as $n => $text) {
            $list[] = ['id' => "s{$s}i" . ($n + 1), 'text' => $text, 'checked' => false];
        }
        $sections[] = ['id' => "s{$s}", 'name' => $name, 'items' => $list];
    }
    return ['rev' => 1, 'sections' => $sections];
}

function sectionIndex(array $state, string $sid): int
{
    foreach ($state['sections'] as $i => $sec) {
        if ($sec['id'] === $sid) return $i;
    }
    fail(404, 'Section not found');
    return -1;
}

function itemIndex(array $section, string $iid): int
{
    foreach ($section['items'] as $i => $item) {
        if ($item['id'] === $iid) return $i;
    }
    fail(404, 'Item not found');
    return -1;
}

// --- open + lock the data file -------------------------------------------
if (!is_dir(DATA_DIR) && !@mkdir(DATA_DIR, 0775, true)) {
    fail(500, 'Cannot create the data folder. Check folder permissions.');
}
$fp = @fopen(DATA_FILE, 'c+');
if (!$fp) {
    fail(500, 'Cannot write data/state.json. Check folder permissions.');
}
flock($fp, LOCK_EX);

$json  = stream_get_contents($fp);
$state = $json ? json_decode($json, true) : null;
$dirty = false;
if (!is_array($state) || !isset($state['sections']) || !is_array($state['sections'])) {
    $state = seed();
    $dirty = true;
}

// --- apply change ----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($in)) fail(400, 'Bad request');

    $action = (string) ($in['action'] ?? '');
    $sid    = (string) ($in['sid'] ?? '');
    $iid    = (string) ($in['iid'] ?? '');

    switch ($action) {
        case 'toggle':
            $si = sectionIndex($state, $sid);
            $ii = itemIndex($state['sections'][$si], $iid);
            $state['sections'][$si]['items'][$ii]['checked'] = (bool) ($in['checked'] ?? false);
            break;

        case 'add':
            $si   = sectionIndex($state, $sid);
            $text = clean($in['text'] ?? '');
            if ($text === '') fail(400, 'Item text is empty');
            $state['sections'][$si]['items'][] = ['id' => newId('i'), 'text' => $text, 'checked' => false];
            break;

        case 'delete':
            $si = sectionIndex($state, $sid);
            $ii = itemIndex($state['sections'][$si], $iid);
            array_splice($state['sections'][$si]['items'], $ii, 1);
            break;

        case 'addSection':
            $name = clean($in['name'] ?? '', 60);
            if ($name === '') fail(400, 'Section name is empty');
            $state['sections'][] = ['id' => newId('s'), 'name' => $name, 'items' => []];
            break;

        case 'deleteSection':
            $si = sectionIndex($state, $sid);
            array_splice($state['sections'], $si, 1);
            break;

        case 'reset':
            foreach ($state['sections'] as &$sec) {
                foreach ($sec['items'] as &$item) {
                    $item['checked'] = false;
                }
                unset($item);
            }
            unset($sec);
            break;

        default:
            fail(400, 'Unknown action');
    }
    $state['rev'] = (int) ($state['rev'] ?? 0) + 1;
    $dirty = true;
}

// --- save + respond --------------------------------------------------------
if ($dirty) {
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, (string) json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fp);
}
flock($fp, LOCK_UN);
fclose($fp);

echo json_encode($state, JSON_UNESCAPED_UNICODE);
