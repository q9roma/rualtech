<?php

return [
    'actions' => [
        'open_actions_menu' => 'Открыть меню действий',
        'toggle_columns' => 'Переключить столбцы',
        'toggle_table_reordering' => 'Переключить пересортировку таблицы',
    ],

    'button' => [
        'label' => 'Действие',
    ],

    'bulk_actions' => [
        'label' => 'Массовые действия',
    ],

    'columns' => [
        'text' => [
            'more_list_items' => 'и еще :count',
        ],
    ],

    'fields' => [
        'bulk_select_page' => [
            'label' => 'Выбрать/отменить выбор всех элементов для массовых действий.',
        ],
        'bulk_select_record' => [
            'label' => 'Выбрать/отменить выбор элемента :key для массовых действий.',
        ],
        'bulk_select_group' => [
            'label' => 'Выбрать/отменить выбор группы :title для массовых действий.',
        ],
        'search' => [
            'label' => 'Поиск',
            'placeholder' => 'Поиск',
            'indicator' => 'Поиск',
        ],
    ],

    'summary' => [
        'heading' => 'Итого',
        'subheadings' => [
            'all' => 'Все :label',
            'group' => ':group итого',
            'page' => 'Эта страница',
        ],
        'summarizers' => [
            'average' => 'Среднее',
            'count' => 'Количество',
            'sum' => 'Сумма',
        ],
    ],

    'actions' => [
        'disable_reordering' => [
            'label' => 'Завершить пересортировку записей',
        ],
        'enable_reordering' => [
            'label' => 'Пересортировать записи',
        ],
        'filter' => [
            'label' => 'Фильтр',
        ],
        'group' => [
            'label' => 'Группировка',
        ],
        'open_bulk_actions' => [
            'label' => 'Открыть действия',
        ],
        'toggle_columns' => [
            'label' => 'Переключить столбцы',
        ],
    ],

    'empty' => [
        'heading' => 'Записи не найдены',
        'description' => 'Создайте :model, чтобы начать.',
    ],

    'filters' => [
        'actions' => [
            'apply' => [
                'label' => 'Применить фильтры',
            ],
            'remove' => [
                'label' => 'Удалить фильтр',
            ],
            'remove_all' => [
                'label' => 'Удалить все фильтры',
            ],
            'reset' => [
                'label' => 'Сбросить',
            ],
        ],
        'heading' => 'Фильтры',
        'indicator' => 'Активные фильтры',
        'multi_select' => [
            'placeholder' => 'Выбрать все',
        ],
        'select' => [
            'placeholder' => 'Выбрать все',
        ],
        'trashed' => [
            'label' => 'Удаленные записи',
            'only_trashed' => 'Только удаленные записи',
            'with_trashed' => 'С удаленными записями',
            'without_trashed' => 'Без удаленных записей',
        ],
    ],

    'grouping' => [
        'fields' => [
            'group' => [
                'label' => 'Группировать по',
                'placeholder' => 'Группировать по',
            ],
            'direction' => [
                'label' => 'Направление группировки',
                'options' => [
                    'asc' => 'По возрастанию',
                    'desc' => 'По убыванию',
                ],
            ],
        ],
    ],

    'reorder_indicator' => 'Перетащите записи в нужном порядке.',

    'selection_indicator' => [
        'selected_count' => '1 запись выбрана|:count записи выбрано|:count записей выбрано',
        'actions' => [
            'select_all' => [
                'label' => 'Выбрать все :count',
            ],
            'deselect_all' => [
                'label' => 'Отменить выбор всех',
            ],
        ],
    ],

    'sorting' => [
        'fields' => [
            'column' => [
                'label' => 'Сортировать по',
            ],
            'direction' => [
                'label' => 'Направление сортировки',
                'options' => [
                    'asc' => 'По возрастанию',
                    'desc' => 'По убыванию',
                ],
            ],
        ],
    ],
];
