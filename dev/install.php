<?php
// Importe la recette comme « MoviePoster » avec la clé TMDB passée dans TMDB_KEY et l'affichage de l'Echo Show
use App\Models\{Plugin, User};
use App\Services\PluginImportService;
use Illuminate\Http\UploadedFile;
Plugin::where('name', 'like', 'MoviePoster%')->get()->each->delete();
$file = new UploadedFile('/tmp/movieposter.zip', 'movieposter.zip', 'application/zip', null, true);
$p = Plugin::find(app(PluginImportService::class)->importFromZip($file, User::find(1))->id);
$p->name = 'MoviePoster';
$p->configuration = ['api_key' => getenv('TMDB_KEY') ?: '', 'region' => 'FR', 'featured' => 'rotate', 'style' => 'color', 'language' => 'fr'];
$p->save();
$p->updateDataPayload();
$d = App\Models\Device::find(2);
$id = App\Services\ImageGenerationService::generateImage($p->fresh()->render('full', true, $d), 2, $p);
copy(storage_path("app/public/images/generated/$id.png"), '/tmp/mp_live.png');
echo "installed id {$p->id}: ", count($p->fresh()->data_payload['IDX_1']['results'] ?? []), " movies this week\n";
