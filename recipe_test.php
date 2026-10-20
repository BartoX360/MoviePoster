<?php
use App\Models\{Plugin, Device, DeviceModel, User};
use App\Services\{PluginImportService, ImageGenerationService};
use Illuminate\Http\UploadedFile;
use Carbon\Carbon;

Plugin::where('name', 'MoviePoster (test)')->get()->each->delete();
$file = new UploadedFile('/tmp/movieposter.zip', 'movieposter.zip', 'application/zip', null, true);
$p = Plugin::find(app(PluginImportService::class)->importFromZip($file, User::find(1))->id);
$base = ['api_key' => 'test', 'region' => 'FR'];
$p->update(['name' => 'MoviePoster (test)', 'configuration' => $base + ['style' => 'eink', 'language' => 'en', 'featured' => 'rotate']]);
Carbon::setTestNow(Carbon::create(2026, 10, 20, 10, 0, 0, 'UTC'));
echo "url=", explode("\n", $p->resolveLiquidVariables($p->polling_url))[0], "\n";

// Sorties TMDB en France figées le 20 octobre 2026 : six semaines du 7 octobre au 17 novembre, en français et en anglais
$feeds = json_decode(file_get_contents('/tmp/test_payload.json'), true);
echo 'payload bytes: ', strlen(json_encode($feeds['fr'])), ' / limit ', Plugin::maxDataPayloadBytesForWire() ?? 'none', "\n";
$noimg = $feeds['fr']; $noimg['IDX_2']['results'][0]['poster_path'] = null; $noimg['IDX_2']['results'][0]['backdrop_path'] = null;
$down = array_fill_keys(['IDX_0', 'IDX_1', 'IDX_2', 'IDX_3', 'IDX_4', 'IDX_5'], ['error' => 'Failed to fetch data']);
$empty = array_fill_keys(array_keys($down), ['page' => 1, 'results' => [], 'total_results' => 0]);
// heure UTC du 20/10 dont le créneau horaire vaut $k modulo 9 (rotation : rubrique = k % 3, rang = k / 3)
$slot = fn($k) => (int) collect(range(0, 23))->first(fn($h) => intdiv(Carbon::create(2026, 10, 20, $h, 0, 0, 'UTC')->timestamp, 3600) % 9 == $k);
// les formats réduits ne sont rendus qu'en e-ink : le mashup de l'écran couleur sort blanc dans LaraPaper (comme pour les autres Poster)
$cases = [
  // nom, taille, modèle, style, langue, affiche, données, heure (UTC), clé
  ['fr_color_hot', 'full', 55, 'color', 'fr', 'hot', $feeds['fr'], 10],
  ['en_eink_hot', 'full', 1, 'eink', 'en', 'hot', $feeds['en'], 10],
  ['fr_color_now', 'full', 55, 'color', 'fr', 'now', $feeds['fr'], 10],
  ['en_eink_now', 'full', 1, 'eink', 'en', 'now', $feeds['en'], 10],
  ['fr_color_upcoming', 'full', 55, 'color', 'fr', 'upcoming', $feeds['fr'], 10],
  ['en_x_upcoming', 'full', 14, 'eink', 'en', 'upcoming', $feeds['en'], 10],
  ['en_color_rotate_hot2', 'full', 55, 'color', 'en', 'rotate', $feeds['en'], $slot(3)],
  ['fr_color_rotate_now3', 'full', 55, 'color', 'fr', 'rotate', $feeds['fr'], $slot(7)],
  ['fr_eink_noimg', 'full', 1, 'eink', 'fr', 'upcoming', $noimg, 10],
  ['en_hh', 'half_horizontal', 1, 'eink', 'en', 'hot', $feeds['en'], 10],
  ['fr_hv', 'half_vertical', 1, 'eink', 'fr', 'now', $feeds['fr'], 10],
  ['en_q', 'quadrant', 1, 'eink', 'en', 'upcoming', $feeds['en'], 10],
  ['fr_q', 'quadrant', 1, 'eink', 'fr', 'hot', $feeds['fr'], 10],
  ['fr_hh', 'half_horizontal', 1, 'eink', 'fr', 'upcoming', $feeds['fr'], 10],
  ['fr_error', 'full', 1, 'eink', 'fr', 'rotate', $down, 10],
  ['fr_nokey', 'full', 1, 'eink', 'fr', 'rotate', $down, 10, ''],
  ['en_empty', 'full', 55, 'color', 'en', 'rotate', $empty, 10],
];
@mkdir('/tmp/mpr'); array_map('unlink', glob('/tmp/mpr/*.png'));
foreach ($cases as $c) {
  [$name, $size, $mid, $style, $lang, $feat, $payload, $h] = $c;
  Carbon::setTestNow(Carbon::create(2026, 10, 20, $h, 0, 0, 'UTC'));
  $p->configuration = ['api_key' => $c[8] ?? 'test', 'region' => 'FR', 'style' => $style, 'language' => $lang, 'featured' => $feat];
  $p->data_payload = $payload; $p->saveQuietly();
  $model = DeviceModel::find($mid);
  $dev = new Device(['name' => 'test']); $dev->setRelation('deviceModel', $model);
  $html = $p->fresh()->render($size, true, $dev);
  if ($name == 'fr_color_hot') file_put_contents('/tmp/mpr/sample.html', $html);
  $pal = $mid == 55 ? App\Models\DevicePalette::find(12) : null;
  $id = ImageGenerationService::generateImageFromModel(markup: $html, deviceModel: $model, palette: $pal);
  copy(storage_path("app/public/images/generated/$id.png"), "/tmp/mpr/$name.png");
  echo "$name ok\n";
}
Carbon::setTestNow();
// flux réel, si une clé est fournie
if (getenv('TMDB_KEY')) {
  $p->configuration = ['api_key' => getenv('TMDB_KEY'), 'region' => 'FR', 'language' => 'fr', 'featured' => 'rotate', 'style' => 'color'];
  $p->saveQuietly(); $p->updateDataPayload();
  $live = $p->fresh()->data_payload;
  echo 'live payload: ', implode(' | ', array_map(fn($w) => isset($w['results']) ? count($w['results']) . ' ' . ($w['results'][0]['title'] ?? '') : json_encode($w), $live)), "\n";
}
