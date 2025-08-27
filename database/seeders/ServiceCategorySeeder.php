<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ServiceCategory;

class ServiceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Техническая поддержка',
                'slug' => 'technical-support',
                'description' => 'Круглосуточная техническая поддержка IT-инфраструктуры, удаленное администрирование, диагностика и устранение неполадок.',
                'icon' => 'heroicon-o-wrench-screwdriver',
                'sort_order' => 1,
                'is_active' => true,
                'seo_title' => 'Техническая поддержка IT | Altech',
                'seo_description' => 'Профессиональная техническая поддержка IT-систем 24/7. Удаленное администрирование, диагностика, устранение неполадок.'
            ],
            [
                'name' => 'Виртуализация',
                'slug' => 'virtualization',
                'description' => 'Проектирование и внедрение решений виртуализации: VMware, Hyper-V, создание отказоустойчивых кластеров.',
                'icon' => 'heroicon-o-server-stack',
                'sort_order' => 2,
                'is_active' => true,
                'seo_title' => 'Виртуализация VMware, Hyper-V | Altech',
                'seo_description' => 'Внедрение и настройка систем виртуализации VMware vSphere, Microsoft Hyper-V. Создание отказоустойчивых кластеров.'
            ],
            [
                'name' => 'Сборка серверов',
                'slug' => 'server-assembly',
                'description' => 'Проектирование, сборка и настройка серверов под конкретные задачи бизнеса. Гарантия и техническая поддержка.',
                'icon' => 'heroicon-o-server',
                'sort_order' => 3,
                'is_active' => true,
                'seo_title' => 'Сборка серверов на заказ | Altech',
                'seo_description' => 'Профессиональная сборка серверов под ваши задачи. Проектирование конфигурации, настройка, гарантия.'
            ],
            [
                'name' => 'Рабочие станции',
                'slug' => 'workstations',
                'description' => 'Сборка и настройка рабочих станций для офиса, дизайнеров, инженеров и специализированных задач.',
                'icon' => 'heroicon-o-computer-desktop',
                'sort_order' => 4,
                'is_active' => true,
                'seo_title' => 'Сборка рабочих станций | Altech',
                'seo_description' => 'Сборка мощных рабочих станций для офиса, дизайна, инженерии. Подбор оптимальной конфигурации под задачи.'
            ],
            [
                'name' => 'Сетевые решения',
                'slug' => 'network-solutions',
                'description' => 'Проектирование и настройка корпоративных сетей, системы мониторинга, управление трафиком.',
                'icon' => 'heroicon-o-signal',
                'sort_order' => 5,
                'is_active' => true,
                'seo_title' => 'Сетевые решения | Altech',
                'seo_description' => 'Проектирование корпоративных сетей, настройка оборудования, системы мониторинга и управления трафиком.'
            ],
            [
                'name' => 'Информационная безопасность',
                'slug' => 'information-security',
                'description' => 'Аудит информационной безопасности, внедрение систем защиты, обучение персонала основам ИБ.',
                'icon' => 'heroicon-o-shield-check',
                'sort_order' => 6,
                'is_active' => true,
                'seo_title' => 'Информационная безопасность | Altech',
                'seo_description' => 'Комплексные решения по информационной безопасности: аудит, внедрение систем защиты, обучение персонала.'
            ]
        ];

        foreach ($categories as $category) {
            ServiceCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}
