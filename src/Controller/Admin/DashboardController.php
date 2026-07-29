<?php

namespace App\Controller\Admin;

use App\Admin\DashboardPanelService;
use App\Demo\DemoStandService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly DashboardPanelService $dashboardPanel,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly DemoStandService $demoStandService,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function index(): Response
    {
        $panel = $this->dashboardPanel->build();
        $request = $this->requestStack->getCurrentRequest();
        $restrictDefault = (string) ($request?->query->get(
            'restrict_up',
            DemoStandService::DEFAULT_RESTRICT_UP_IF_ROAS_BELOW,
        ) ?? DemoStandService::DEFAULT_RESTRICT_UP_IF_ROAS_BELOW);

        return $this->render('admin/dashboard.html.twig', [
            'panel' => $panel,
            'restrictUpDefault' => $restrictDefault,
            'demoRoas' => '4.0',
            'allowUpDefault' => '5.0',
            'urls' => [
                'campaigns' => $this->adminUrlGenerator->setController(CampaignCrudController::class)->generateUrl(),
                'decisions' => $this->adminUrlGenerator->setController(BidDecisionCrudController::class)->generateUrl(),
                'campaignNew' => $this->adminUrlGenerator
                    ->setController(CampaignCrudController::class)
                    ->setAction('new')
                    ->generateUrl(),
                'demoStand' => $this->generateUrl('admin_demo_stand'),
            ],
        ]);
    }

    #[Route('/admin/demo-stand', name: 'admin_demo_stand', methods: ['POST'])]
    public function runDemoStand(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('demo_stand', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Неверный CSRF-токен. Обновите страницу и попробуйте снова.');

            return $this->redirectToRoute('admin');
        }

        $reset = $request->request->getBoolean('reset');
        $restrictInput = (string) $request->request->get(
            'restrict_up_if_roas_below',
            DemoStandService::DEFAULT_RESTRICT_UP_IF_ROAS_BELOW,
        );

        try {
            $result = $this->demoStandService->run($reset, $restrictInput);

            $this->addFlash(
                'success',
                sprintf(
                    'Демо-стенд готов: кампания «%s» (id=%d), режим %s, ROAS %s, restrict_up=%s, %d решений (dry-run).',
                    $result->campaignName,
                    $result->campaignId,
                    $result->campaignMode->value,
                    $result->roas ?? '—',
                    $result->restrictUpIfRoasBelow,
                    $result->decisionsCount,
                ),
            );

            return $this->redirectToRoute('admin', ['restrict_up' => $result->restrictUpIfRoasBelow]);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('admin', ['restrict_up' => $restrictInput]);
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Ошибка запуска демо-стенда: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('WB Bidder');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Панель', 'fa fa-home');
        yield MenuItem::linkTo(CampaignCrudController::class, 'Кампании', 'fa fa-bullhorn');
        yield MenuItem::linkTo(BidDecisionCrudController::class, 'История ставок', 'fa fa-list');
    }
}
