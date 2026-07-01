<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;
use Shared\SalesOutletsDomain\Enums\HeadOrganizationType;
use Shared\SalesOutletsDomain\Enums\SalesOutletStatus;

/**
 * Заполняет таблицу sales_outlets тестовыми данными для Feature-тестов API.
 * Id записей начинаются с 1001, чтобы не пересекаться с seed service-a.
 */
final class SalesOutletTestSeeder
{
    /** Вставляет фикстуры торговых точек через DBAL (без Doctrine Entity). */
    public static function seed(EntityManagerInterface $entityManager): void
    {
        $connection = $entityManager->getConnection();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach (self::rows() as $row) {
            $connection->insert('sales_outlets', [
                'id' => $row['id'],
                'shop' => $row['shop'],
                'manager' => $row['manager'],
                'curator' => $row['curator'],
                'name' => $row['name'],
                'inn' => $row['inn'],
                'head_organization' => $row['head_organization'],
                'head_organization_type' => $row['head_organization_type']->value,
                'organization_name' => $row['organization_name'],
                'status' => $row['status']->value,
                'approved' => $row['approved'],
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
                'user_id' => null,
            ]);
        }
    }

    /**
     * @return list<array{
     *     id: int,
     *     shop: string,
     *     manager: string,
     *     curator: string,
     *     name: string,
     *     inn: string,
     *     head_organization: string,
     *     head_organization_type: HeadOrganizationType,
     *     organization_name: string,
     *     status: SalesOutletStatus,
     *     approved: string
     * }>
     */
    private static function rows(): array
    {
        return [
            [
                'id' => 1001,
                'shop' => 'Белгород',
                'manager' => 'Брюхненко С. Ю.',
                'curator' => 'Коршунов М. С.',
                'name' => 'Колхоз',
                'inn' => '311300065310',
                'head_organization' => 'ИП Рудев Востин Минович',
                'head_organization_type' => HeadOrganizationType::IndividualEntrepreneur,
                'organization_name' => 'ИП Рудев Востин Игорь Васильевич',
                'status' => SalesOutletStatus::Blocked,
                'approved' => 'Нет',
            ],
            [
                'id' => 1002,
                'shop' => 'Белгород',
                'manager' => 'Брюхненко С. Ю.',
                'curator' => 'Коршунов М. С.',
                'name' => 'Колхоз',
                'inn' => '311800277237',
                'head_organization' => 'ИП Майгатов Любовь Александровна',
                'head_organization_type' => HeadOrganizationType::IndividualEntrepreneur,
                'organization_name' => 'ИП Майгатова Любовь Александровна',
                'status' => SalesOutletStatus::Blocked,
                'approved' => 'Нет',
            ],
            [
                'id' => 1003,
                'shop' => 'Воронеж',
                'manager' => 'Морозова Е. А.',
                'curator' => 'Поляков А. В.',
                'name' => 'Северный',
                'inn' => '3662111223',
                'head_organization' => 'ООО Северный продукт',
                'head_organization_type' => HeadOrganizationType::LimitedLiabilityCompany,
                'organization_name' => 'ООО Северный продукт',
                'status' => SalesOutletStatus::Review,
                'approved' => 'Частично',
            ],
            [
                'id' => 1004,
                'shop' => 'Курск',
                'manager' => 'Семенов И. П.',
                'curator' => 'Лебедева А. Н.',
                'name' => 'Центральный',
                'inn' => '4632014589',
                'head_organization' => 'ООО Центральная сеть',
                'head_organization_type' => HeadOrganizationType::LimitedLiabilityCompany,
                'organization_name' => 'ООО Центральная сеть',
                'status' => SalesOutletStatus::Approved,
                'approved' => 'Да',
            ],
            [
                'id' => 1005,
                'shop' => 'Липецк',
                'manager' => 'Иванова М. А.',
                'curator' => 'Андреев К. О.',
                'name' => 'Южный',
                'inn' => '4825098765',
                'head_organization' => 'АО Южная торговля',
                'head_organization_type' => HeadOrganizationType::JointStockCompany,
                'organization_name' => 'АО Южная торговля',
                'status' => SalesOutletStatus::Review,
                'approved' => 'Частично',
            ],
            [
                'id' => 1006,
                'shop' => 'Орел',
                'manager' => 'Кузнецов Р. Д.',
                'curator' => 'Петрова Л. С.',
                'name' => 'Партнер',
                'inn' => '5753123456',
                'head_organization' => 'ИП Ковалев Сергей Петрович',
                'head_organization_type' => HeadOrganizationType::IndividualEntrepreneur,
                'organization_name' => 'ИП Ковалев Сергей Петрович',
                'status' => SalesOutletStatus::Approved,
                'approved' => 'Да',
            ],
            [
                'id' => 1007,
                'shop' => 'Тамбов',
                'manager' => 'Громова Н. И.',
                'curator' => 'Смирнов Д. Е.',
                'name' => 'Оптима',
                'inn' => '6829012345',
                'head_organization' => 'ООО Оптима маркет',
                'head_organization_type' => HeadOrganizationType::LimitedLiabilityCompany,
                'organization_name' => 'ООО Оптима маркет',
                'status' => SalesOutletStatus::Blocked,
                'approved' => 'Нет',
            ],
            [
                'id' => 1008,
                'shop' => 'Старый Оскол',
                'manager' => 'Фролов П. С.',
                'curator' => 'Никитина Ю. В.',
                'name' => 'Фермер',
                'inn' => '3128012345',
                'head_organization' => 'СПК Фермер',
                'head_organization_type' => HeadOrganizationType::AgriculturalCooperative,
                'organization_name' => 'СПК Фермер',
                'status' => SalesOutletStatus::Approved,
                'approved' => 'Да',
            ],
        ];
    }
}
