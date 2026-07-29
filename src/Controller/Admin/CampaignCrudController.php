<?php

namespace App\Controller\Admin;

use App\Admin\ColumnHelpLabel;
use App\Entity\Campaign;
use App\Service\CampaignRemovalService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CampaignCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Campaign::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Кампания')
            ->setEntityLabelInPlural('Кампании')
            ->setDefaultSort(['id' => 'DESC'])
            ->setTimezone('Europe/Moscow')
            ->setDateTimeFormat('dd.MM.yyyy HH:mm', DateTimeField::FORMAT_NONE);
    }

    public function configureFields(string $pageName): iterable
    {
        $isDetail = $pageName === Crud::PAGE_DETAIL;

        yield $this->openSection('Основные', null, $isDetail);

        yield TextField::new('name')
            ->setLabel(ColumnHelpLabel::make(
                'Название',
                'Внутреннее имя кампании в WB Bidder. Не влияет на логику — только для навигации в админке.',
            ));

        yield IntegerField::new('wbAdvertId')
            ->setLabel(ColumnHelpLabel::make(
                'WB ID',
                'ID рекламной кампании в кабинете Wildberries (advertId). Используется для запросов fullstats, normquery и установки ставок.',
            ));

        yield BooleanField::new('active')
            ->setLabel(ColumnHelpLabel::make(
                'Активна',
                'Неактивные кампании не синхронизируются и не участвуют в автобиддинге.',
            ));

        yield BooleanField::new('biddingEnabled')
            ->setLabel(ColumnHelpLabel::make(
                'Автобиддинг',
                'Разрешает расчёт решений по ставкам и (если не dry-run) отправку в WB через очередь.',
            ));

        yield BooleanField::new('dryRun')
            ->setLabel(ColumnHelpLabel::make(
                'Dry-run',
                'Решения сохраняются в истории, но ставки не отправляются в Wildberries. Безопасный режим для проверки логики.',
            ));

        yield $this->openSection(
            'Уровень 1: Кампания (ROAS)',
            'Смотрим на всю кампанию. Низкий ROAS → режим DEFENSIVE (не поднимаем ставки на кластерах). Выручка из WB fullstats — точная.',
            $isDetail,
        );

        yield BooleanField::new('level1Enabled')
            ->setLabel(ColumnHelpLabel::make(
                'Включён',
                'Включает уровень 1: расчёт ROAS кампании и определение режима DEFENSIVE / BALANCED / GROWTH.',
            ));

        yield NumberField::new('targetRoas')
            ->setLabel(ColumnHelpLabel::make(
                'Целевой ROAS',
                'Ориентир окупаемости кампании. В текущей стратегии режим задаётся порогами restrict_up и allow_up, не этим полем.',
            ));

        yield IntegerField::new('metricsWindowDays')
            ->setLabel(ColumnHelpLabel::make(
                'Окно (дней)',
                'Сколько последних дней daily stats агрегируются для ROAS и CPA перед расчётом ставок.',
            ));

        yield NumberField::new('restrictUpIfRoasBelow')
            ->setLabel(ColumnHelpLabel::make(
                'ROAS ниже → DEFENSIVE',
                'Если ROAS кампании ниже этого значения — режим DEFENSIVE: повышения ставок на кластерах блокируются (UP → HOLD).',
            ));

        yield NumberField::new('allowUpIfRoasAbove')
            ->setLabel(ColumnHelpLabel::make(
                'ROAS выше → GROWTH',
                'Если ROAS кампании не ниже этого значения — режим GROWTH: разрешены более смелые повышения (growth max up %).',
            ));

        yield $this->openSection(
            'Уровень 2: Кластеры (CPA)',
            'Для каждого поискового кластера (norm_query). CPA = расход ÷ заказы. Гранулярность «фраза» не поддерживается WB API.',
            $isDetail,
        );

        yield BooleanField::new('level2Enabled')
            ->setLabel(ColumnHelpLabel::make(
                'Включён',
                'Включает уровень 2: для каждого кластера CPA сравнивается с target и формируется proposal up/down/hold/pause.',
            ));

        yield NumberField::new('targetCpa')
            ->setLabel(ColumnHelpLabel::make(
                'Целевой CPA, ₽',
                'Желаемая цена заказа на кластер. CPA ниже target → предложение поднять ставку, выше → снизить или pause.',
            ));

        yield NumberField::new('cpaBuffer')
            ->setLabel(ColumnHelpLabel::make(
                'CPA buffer, ₽',
                'Мёртвая зона вокруг target CPA. Внутри buffer ставка не меняется (hold), чтобы не дёргать ставки на шуме.',
            ));

        yield NumberField::new('pauseIfCpaAbove')
            ->setLabel(ColumnHelpLabel::make(
                'Pause если CPA выше, ₽',
                'Если CPA кластера выше этого порога и есть расход — proposal pause (сильная защита от перерасхода на кластере).',
            ));

        yield IntegerField::new('minOrders')
            ->setLabel(ColumnHelpLabel::make(
                'Min заказов',
                'Минимум заказов в окне для кластера. Меньше — hold: данных недостаточно для надёжного CPA.',
            ));

        yield IntegerField::new('minImpressions')
            ->setLabel(ColumnHelpLabel::make(
                'Min показов',
                'Минимум показов в окне для кластера. Меньше — hold: статистика слишком шумная.',
            ));

        yield $this->openSection('Предохранители', null, $isDetail);

        yield IntegerField::new('minBidKopecks')
            ->setLabel(ColumnHelpLabel::make(
                'Min ставка (коп.)',
                'Нижняя граница ставки после любого изменения. Защита от обнуления ставки.',
            ));

        yield IntegerField::new('maxBidKopecks')
            ->setLabel(ColumnHelpLabel::make(
                'Max ставка (коп.)',
                'Верхняя граница ставки. Новая ставка не может превысить это значение.',
            ));

        yield IntegerField::new('maxChangeUpPct')
            ->setLabel(ColumnHelpLabel::make(
                'Max рост %',
                'Максимальный процент повышения ставки за один шаг в режимах BALANCED и DEFENSIVE (если up разрешён).',
            ));

        yield IntegerField::new('maxChangeDownPct')
            ->setLabel(ColumnHelpLabel::make(
                'Max снижение %',
                'Максимальный процент снижения ставки за один шаг.',
            ));

        yield IntegerField::new('growthMaxChangeUpPct')
            ->setLabel(ColumnHelpLabel::make(
                'Max рост в GROWTH %',
                'Максимальный процент повышения в режиме GROWTH — обычно выше обычного max роста.',
            ));

        yield IntegerField::new('cooldownHours')
            ->setLabel(ColumnHelpLabel::make(
                'Cooldown (ч)',
                'Минимальный интервал между изменениями ставки на одном кластере. Предотвращает частые переключения.',
            ));
    }

  /**
   * На странице просмотра — fieldsets (все секции видны сразу).
   * В формах — tabs.
   */
    private function openSection(string $label, ?string $help, bool $isDetail): FormField
    {
        if ($isDetail) {
            $fieldset = FormField::addFieldset($label);
            if ($help !== null) {
                $fieldset->setHelp($help);
            }

            return $fieldset;
        }

        $tab = FormField::addTab($label);
        if ($help !== null) {
            $tab->setHelp($help);
        }

        return $tab;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions;
    }

    public function deleteEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        if ($entityInstance instanceof Campaign) {
            $this->container->get(CampaignRemovalService::class)->delete($entityInstance);

            return;
        }

        $entityManager->remove($entityInstance);
        $entityManager->flush();
    }
}
