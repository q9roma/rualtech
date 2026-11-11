<?php
namespace App\Admin;

use App\Models\CatalogPriceUpload;
use Illuminate\Support\Facades\Storage;

class CatalogPriceSafety
{
    public static function inspect(CatalogPriceUpload $upload, array $rows): array
    {
        $latest=CatalogPriceUpload::where('source_profile',$upload->source_profile)->where('status','completed')
            ->where('id','!=',$upload->id)->latest('updated_at')->orderByDesc('id')->first();
        $blocked=[]; $warnings=[];
        if (!$rows) $blocked[]='Пустой прайс нельзя применять.';
        if ($latest && $latest->file_hash === $upload->file_hash) $blocked[]='Этот файл уже применён. Повторное обновление не требуется.';
        elseif (CatalogPriceUpload::where('source_profile',$upload->source_profile)->where('status','completed')->where('file_hash',$upload->file_hash)->where('id','!=',$upload->id)->exists()) {
            $blocked[]='Этот файл уже применялся раньше. Его повторное применение может вернуть устаревшие данные.';
        }
        if ($latest && (($upload->price_date && $latest->price_date && $upload->price_date < $latest->price_date) || $upload->created_at->lt($latest->created_at))) {
            $blocked[]='Есть более новый применённый прайс. Загрузите актуальный файл.';
        }
        if (!$upload->price_date) $warnings[]='Дата прайса не указана: возраст исходного файла не подтверждён.';
        elseif (\Carbon\Carbon::parse($upload->price_date)->lt(today()->subDays(2))) $warnings[]='Прайс старше двух дней. Проверьте актуальность данных.';
        if ($latest) {
            $path=$latest->directory().'/staged-products.json';
            if (!Storage::disk('local')->exists($path)) $blocked[]='Не найден предыдущий снимок для проверки полноты.';
            else {
                $json=Storage::disk('local')->get($path);
                if (!hash_equals($latest->staged_hash ?? '',hash('sha256',$json))) $blocked[]='Предыдущий снимок не прошёл проверку целостности.';
                else {
                    $old=json_decode($json,true,flags:JSON_THROW_ON_ERROR);
                    if (!$old) $blocked[]='Предыдущий снимок пуст: полноту нового файла нельзя подтвердить.';
                    $key=fn($r)=>CatalogDraftImport::itemKey($upload->source_profile,$r);
                    $before=array_unique(array_map($key,$old)); $after=array_unique(array_map($key,$rows));
                    $missing=count(array_diff($before,$after));
                    if (count($before) && $missing/count($before)>=0.3) $warnings[]="Из прайса исчезло {$missing} из ".count($before).' позиций (не менее 30%). Проверьте, что файл полный.';
                }
            }
        }
        return ['blocked'=>$blocked,'warnings'=>$warnings,'token'=>hash('sha256',json_encode([$upload->file_hash,$upload->fingerprint,$latest?->id,$latest?->updated_at,$blocked,$warnings]))];
    }
}
