<?php
// Copie le code de « MoviePoster (test) » dans le plugin installé, sans toucher à ses réglages
use App\Models\Plugin;
$src = Plugin::where('name', 'MoviePoster (test)')->firstOrFail();
$dst = Plugin::where('name', 'MoviePoster')->firstOrFail();
$dst->fill($src->only(['render_markup', 'render_markup_shared', 'render_markup_half_horizontal', 'render_markup_half_vertical',
  'render_markup_quadrant', 'polling_url', 'polling_verb', 'polling_header', 'configuration_template', 'no_bleed', 'markup_language']));
$dst->save();
$src->delete();
echo "updated {$dst->id}\n";
