<!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Проверка фотографий — Алтех</title>
<style>
body{margin:0;background:#f3f4f6;color:#17202b;font:16px/1.5 system-ui,sans-serif}main{max-width:1440px;padding:32px 24px;margin:auto}a{color:#2158a5}h1{margin:12px 0}h2{margin-top:32px}p{max-width:1000px}.notice{padding:16px;border:1px solid #d3dce8;background:#fff;border-radius:12px}.stats{display:flex;gap:16px;flex-wrap:wrap;margin:24px 0}.stat{background:white;border:1px solid #ddd;border-radius:12px;padding:20px;min-width:180px}.stat strong{font-size:30px;display:block}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,330px),1fr));gap:20px}.card{background:white;padding:20px;border:1px solid #dce0e6;border-radius:12px;min-width:0}.cover{width:100%;height:280px;object-fit:contain;background:#fff}.thumbs{display:flex;gap:8px;overflow:auto}.thumbs img{width:72px;height:72px;object-fit:contain;border:1px solid #ddd;border-radius:6px}.sku{font-size:14px;color:#526070;overflow-wrap:anywhere}.badge{display:inline-block;padding:4px 10px;border-radius:6px;font-size:13px;background:#e7eef8}.review{background:#fff0d2}.flags{font-size:14px;color:#76510a;padding-left:20px}.error{color:#a51a24}summary{cursor:pointer;font-weight:600}table{border-collapse:collapse;background:white;width:100%}th,td{text-align:left;padding:12px;border-bottom:1px solid #ddd}nav{display:flex;gap:20px;flex-wrap:wrap}.source-title{font-size:13px;color:#526070}details{margin:16px 0}
</style></head><body><main>
<nav><a href="/catalog">Каталог</a><a href="/catalog/photo-preview">Предыдущая тестовая галерея</a><a href="#confirmed">10 Philips</a><a href="#review">Галерея LG/Philips</a></nav>
<h1>Проверка фотографий</h1>
@if($quality ?? null)
<p class="notice"><strong>Проверенные файлы: {{ $quality['counts']['combined']['count'] }} из {{ $quality['denominator'] }} — {{ $quality['counts']['combined']['percent'] }}%.</strong> <a href="/catalog/photo-quality">Открыть тестовые карточки VVP + Icecat и отчёт о качестве</a>. Ниже сохранён отдельный расчёт покрытия источниками.</p>
@endif
<p class="notice">Отдельная локальная тестовая галерея. Фото не назначены товарам, публикации и рабочие карточки не изменены. Изображения можно открыть в полном размере.</p>
<h2>Покрытие актуального каталога</h2>
<p>База расчёта: {{ $coverage['denominator'] }} товаров из актуального прайса, включая черновики. Это найденные источники фото, а не число карточек с опубликованными изображениями.</p>
<div class="stats">
@foreach(['vvp'=>'VVP: ссылки на фото', 'icecat_confirmed'=>'Icecat: подтверждённые', 'combined'=>'Вместе, без повторов', 'uncovered'=>'Пока не покрыто'] as $key=>$label)
<div class="stat">{{ $label }}<strong>{{ $coverage['counts'][$key]['percent'] }}%</strong>{{ $coverage['counts'][$key]['count'] }} товаров</div>
@endforeach
</div>
<p>В обоих источниках: {{ $coverage['counts']['intersection']['count'] }}. Icecat добавляет к VVP: {{ $coverage['counts']['icecat_adds']['count'] }}. Проверено через Icecat {{ $coverage['counts']['icecat_tested']['count'] }} товаров из {{ $coverage['denominator'] }}. {{ ($coverage['scope'] ?? '') === 'full-current-catalog' ? 'Полный проход по актуальному каталогу завершён.' : 'Это ещё не полный проход по каталогу.' }}</p>
<p class="notice">Для VVP посчитаны точные совпадения идентификаторов со ссылками в XML; все файлы VVP ещё не скачаны и не проверены визуально. Для Icecat — совпадения EAN либо отдельно проверенные полный артикул и характеристики модели, с загруженными изображениями; визуальное соответствие и комплектация требуют отдельной оценки. {{ $coverage['counts']['icecat_review_only']['count'] }} спорных позиций не включены в подтверждённое покрытие.</p>
@if(isset($coverage['sections']))
<h2>Покрытие по разделам</h2>
<div style="overflow-x:auto"><table><thead><tr><th>Раздел</th><th>Всего</th><th>VVP</th><th>Icecat</th><th>Вместе</th><th>Покрытие</th></tr></thead><tbody>
@foreach($coverage['sections'] as $section)<tr><td>{{ $section['name'] }}</td><td>{{ $section['total'] }}</td><td>{{ $section['vvp'] }}</td><td>{{ $section['icecat'] }}</td><td>{{ $section['combined'] }}</td><td>{{ $section['percent'] }}%</td></tr>@endforeach
</tbody></table></div>
<p>Демонстрационный доступ Icecat: не найдено — {{ $coverage['statuses']['not_found'] ?? 0 }}, доступ ограничен — {{ $coverage['statuses']['restricted'] ?? 0 }}, ошибки запросов — {{ $coverage['statuses']['error'] ?? 0 }}. Это результаты проверенного доступа, а не утверждение об отсутствии товара в полной базе Icecat. Конфликтов EAN: {{ $coverage['ean_conflicts'] }}. Точных совпадений без изображения в ответе: {{ $coverage['strict_matches_without_image'] ?? 0 }}. Ошибок загрузки подтверждённых фото: {{ $coverage['download_errors'] }}.</p>
<p>Ниже сохранена отдельная галерея предыдущей выборки из 67 LG/Philips. Проценты выше рассчитаны по всему актуальному каталогу.</p>
@endif
@if(count($decisions ?? []))
<h2 id="audit">Разбор 71 кандидата</h2>
<p>Подтверждено по EAN: {{ $coverage['counts']['icecat_ean_confirmed']['count'] ?? 0 }}. Дополнительно по модели: {{ $coverage['counts']['icecat_mpn_confirmed']['count'] ?? 0 }}. Подтверждение модели не означает проверку комплектации, аппаратной ревизии или всех ракурсов.</p>
<div style="overflow-x:auto"><table><thead><tr><th>Товар</th><th>Результат</th><th>Основание и источники</th></tr></thead><tbody>
@foreach($decisions as $decision)
<tr><td>{{ $decision['brand'] }} {{ $decision['sku'] }}<details><summary>Описание прайса</summary>{{ $decision['name'] }}</details></td><td>{{ $decision['outcome'] === 'confirmed' ? 'Модель подтверждена' : 'Не включено' }}</td><td>{{ $decision['reason'] }}
<details><summary>Доказательства</summary><p>{{ $decision['icecat_title'] }}</p>
@foreach($decision['sources'] as $source)<p><a href="{{ $source }}" target="_blank" rel="noopener">{{ $loop->first ? 'Ответ Icecat' : 'Производитель' }}</a></p>@endforeach
@foreach($decision['features'] as $key=>$feature)<div>{{ $key }}: {{ $feature['value'] }}</div>@endforeach
</details></td></tr>
@endforeach
</tbody></table></div>
@endif
@foreach([true=>'Подтверждённые по EAN Philips', false=>'LG и Philips: галерея моделей'] as $strict=>$heading)
<h2 id="{{ $strict ? 'confirmed' : 'review' }}">{{ $heading }}</h2>
<p>{{ $strict ? 'Идентификаторы повторно проверены; комплектацию и визуальное соответствие всё равно оцениваем отдельно.' : 'Первоначальная выборка. Итог проверки указан в таблице выше; исходные замечания сохранены для сравнения.' }}</p>
<div class="grid">
@foreach($rows as $row)
@continue((bool)$row['usable_candidate'] !== (bool)$strict)
<article class="card">
<span class="badge {{ $strict ? '' : 'review' }}">{{ $strict ? 'EAN совпал' : (isset($coverage['counts']['icecat_mpn_confirmed']) && in_array($row['id'], $coverage['counts']['icecat_mpn_confirmed']['ids'] ?? []) ? 'Модель подтверждена' : 'Требуется уточнение') }}</span>
@if(count($row['review_images']))
<a href="{{ route('catalog.photo-review-image', $row['review_images'][0]['file']) }}" target="_blank" rel="noopener"><img class="cover" loading="lazy" src="{{ route('catalog.photo-review-image', $row['review_images'][0]['file']) }}" alt="Пробное фото {{ $row['brand'] }} {{ $row['sku'] }}"></a>
<div class="thumbs">@foreach($row['review_images'] as $image)<a href="{{ route('catalog.photo-review-image', $image['file']) }}" target="_blank" rel="noopener"><img loading="lazy" src="{{ route('catalog.photo-review-image', $image['file']) }}" alt="{{ $row['sku'] }} — ракурс {{ $loop->iteration }}" title="{{ $image['width'] }} × {{ $image['height'] }}"></a>@endforeach</div>
@endif
<h3>{{ $row['name'] }}</h3><p class="sku">{{ $row['brand'] }} · {{ $row['sku'] }} · ID {{ $row['id'] }}</p>
<details><summary>Сопоставление с Icecat</summary><p class="source-title">{{ $row['fresh_title'] ?? $row['title'] }}</p><p class="sku">EAN прайса: {{ $row['ean'] ?: 'не указан' }}<br>EAN Icecat: {{ implode(', ', $row['fresh_gtins'] ?? []) ?: 'не указан' }}</p></details>
@if(count($row['review_flags']))<ul class="flags">@foreach($row['review_flags'] as $flag)<li>{{ $flag }}</li>@endforeach</ul>@endif
@foreach($row['review_errors'] as $error)<p class="error">{{ $error }}</p>@endforeach
</article>
@endforeach
</div>
@endforeach
</main></body></html>
