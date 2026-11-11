<?php

return [
    // Prototype is opt-in outside local; enabling it is not production acceptance.
    'enabled' => env('LUNAR_PROTOTYPE_ENABLED', env('APP_ENV') === 'local'),
    'max_quantity' => 99,
    'max_lines' => 50,
    'pickup' => ['directions' => 'Заезд с Хлебозаводской улицы через шлагбаум. На въезде скажите, что вы в Алтех, — вас пропустят.', 'postcode' => '141007', 'city' => 'Мытищи', 'address' => 'Московская область, г. Мытищи, Хлебозаводская ул., влд. 4а стр. 1'],
    'carriers' => ['cdek' => 'СДЭК', 'dellin' => 'Деловые Линии', 'pek' => 'ПЭК', 'post' => 'Почта России', 'dpd' => 'DPD'],
];
