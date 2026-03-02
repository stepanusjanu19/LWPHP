<?php

namespace Kei\Lwphp\Livewire;

use Kei\Lwphp\Service\LandingFeatureService;
use Kei\Lwphp\Service\TestimonialService;
use Kei\Lwphp\Service\HeroSettingService;

class LandingManagerComponent extends Component
{
    public string $activeTab = 'features';

    private LandingFeatureService $featureService;
    private TestimonialService $testimonialService;
    private HeroSettingService $heroSettingService;

    public function __construct(
        LandingFeatureService $featureService,
        TestimonialService $testimonialService,
        HeroSettingService $heroSettingService
    ) {
        parent::__construct();
        $this->featureService = $featureService;
        $this->testimonialService = $testimonialService;
        $this->heroSettingService = $heroSettingService;
    }

    public function switchTabToFeatures(): void
    {
        $this->activeTab = 'features';
    }

    public function switchTabToTestimonials(): void
    {
        $this->activeTab = 'testimonials';
    }

    public function switchTabToHero(): void
    {
        $this->activeTab = 'hero';
    }

    public function saveHeroSettings(array $payload): void
    {
        $this->heroSettingService->updateSettings([
            'headline' => $payload['headline'] ?? '',
            'subheadline' => $payload['subheadline'] ?? '',
            'buttonText' => $payload['buttonText'] ?? '',
            'buttonUrl' => $payload['buttonUrl'] ?? ''
        ]);
        $this->activeTab = 'hero'; // Stay on tab
    }

    public function render(): string
    {
        return 'livewire/landing_manager.twig';
    }

    /**
     * Pass necessary variables to the Twig view
     */
    public function getTemplateVariables(): array
    {
        $data = ['activeTab' => $this->activeTab];

        if ($this->activeTab === 'features') {
            $data['features'] = $this->featureService->getAll();
        } elseif ($this->activeTab === 'testimonials') {
            $data['testimonials'] = $this->testimonialService->getAll();
        } elseif ($this->activeTab === 'hero') {
            $data['hero'] = $this->heroSettingService->getSettings();
        }

        return $data;
    }
}
