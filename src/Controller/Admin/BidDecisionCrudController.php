<?php

namespace App\Controller\Admin;

use App\Entity\BidDecision;
use App\Enum\BidAction;
use App\Enum\BidDecisionStatus;
use App\Enum\CampaignMode;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class BidDecisionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return BidDecision::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Решение')
            ->setEntityLabelInPlural('История ставок')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['reason']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('campaign');
        yield AssociationField::new('cluster');
        yield TextField::new('campaignMode')
            ->setLabel('Режим кампании')
            ->formatValue(static fn (?CampaignMode $value): string => $value?->value ?? '—');
        yield TextField::new('proposalAction')
            ->setLabel('Proposal (Уровень 2)')
            ->formatValue(static fn (?BidAction $value): string => $value?->value ?? '—');
        yield TextField::new('finalAction')
            ->setLabel('Final')
            ->formatValue(static fn (?BidAction $value): string => $value?->value ?? '—');
        yield IntegerField::new('oldBidKopecks')->setLabel('Старая ставка');
        yield IntegerField::new('newBidKopecks')->setLabel('Новая ставка');
        yield TextField::new('reason');
        yield TextField::new('status')
            ->formatValue(static fn (?BidDecisionStatus $value): string => $value?->value ?? '—');
        yield DateTimeField::new('createdAt');
        yield DateTimeField::new('appliedAt');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE);
    }
}
