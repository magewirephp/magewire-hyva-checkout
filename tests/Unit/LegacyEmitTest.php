<?php

declare(strict_types=1);

namespace Magewirephp\MagewireHyvaCheckout\Tests\Unit;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Interception\Code\Generator\Interceptor as InterceptorGenerator;
use Magento\Framework\Interception\DefinitionInterface;
use Magento\Framework\Interception\PluginListInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Magewirephp\Magewire\ApplicationContainer;
use Magewirephp\Magewire\EventBus;
use Magewirephp\Magewire\Features\SupportEvents\SupportEvents;
use Magewirephp\Magewire\Mechanisms\ComponentRegistry;
use Magewirephp\Magewire\Mechanisms\DataStore;
use Magewirephp\Magewire\Mechanisms\HandleComponents\ComponentContext;
use Magewirephp\Magewire\Mechanisms\HandleComponents\ComponentContext\Effects;
use Magewirephp\Magewire\Mechanisms\HandleComponents\ComponentContext\Memo;
use Magewirephp\Magewire\Mechanisms\ResolveComponents\Layout\LayoutLifecycle;
use Magewirephp\Magewire\Mechanisms\ResolveComponents\Management\LayoutLifecycleManager;
use Magewirephp\MagewireHyvaCheckout\Magewire\Features\SupportHyvaCheckoutBackwardsCompatibility\BackwardsCompatibility;
use Magewirephp\MagewireHyvaCheckout\Magewire\Features\SupportHyvaCheckoutBackwardsCompatibility\SupportHyvaCheckoutBackwardsCompatibility;
use Magewirephp\MagewireHyvaCheckout\Plugin\Magewire\LegacyEmit;
use Magewirephp\MagewireHyvaCheckout\Tests\Fixtures\EventComponent;
use Magewirephp\MagewireHyvaCheckout\Tests\Fixtures\OptedInComponent;
use Magewirephp\MagewireHyvaCheckout\Tests\Fixtures\OptedOutComponent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionProperty;

use function Magewirephp\Magewire\store;

class LegacyEmitTest extends TestCase
{
    private ?ObjectManagerInterface $previousObjectManager;
    private BackwardsCompatibility $backwardsCompatibility;
    private bool $withinCheckout = false;

    public static function setUpBeforeClass(): void
    {
        // Exercise Magento's generated signatures and real plugin chain, not a hand-written proxy.
        foreach ([EventComponent::class, OptedInComponent::class, OptedOutComponent::class] as $class) {
            $generator = new class($class, $class . '\\Interceptor') extends InterceptorGenerator {
                public function source(): string
                {
                    return $this->_generateCode();
                }
            };

            eval($generator->source());
        }
    }

    protected function setUp(): void
    {
        $this->previousObjectManager = (new ReflectionProperty(ObjectManager::class, '_instance'))->getValue();
        $lifecycle = $this->createMock(LayoutLifecycle::class);
        $lifecycle->method('within')->with('hyva-checkout-main')->willReturnCallback(fn () => $this->withinCheckout);
        $manager = $this->createMock(LayoutLifecycleManager::class);
        $manager->method('forMagewire')->willReturn($lifecycle);
        $this->backwardsCompatibility = new BackwardsCompatibility($manager);
        $plugin = new LegacyEmit($this->backwardsCompatibility);

        $plugins = $this->createMock(PluginListInterface::class);
        $plugins->method('getPlugin')->willReturn($plugin);
        $plugins->method('getNext')->willReturnCallback(static function ($type, $method, $code = null) use ($plugin) {
            if ($code !== null) {
                return null;
            }
            if (method_exists($plugin, 'before' . ucfirst($method))) {
                return [DefinitionInterface::LISTENER_BEFORE => ['hyva_checkout_legacy_emit']];
            }
            if (method_exists($plugin, 'around' . ucfirst($method))) {
                return [DefinitionInterface::LISTENER_AROUND => 'hyva_checkout_legacy_emit'];
            }
            return null;
        });

        $dataStore = new DataStore();
        $eventBus = new EventBus();
        $registry = $this->createMock(ComponentRegistry::class);
        $registry->method('getName')->willReturnArgument(0);
        $container = $this->createMock(ApplicationContainer::class);
        $container->method('make')->with(ComponentRegistry::class)->willReturn($registry);
        $manager = $this->createMock(ObjectManagerInterface::class);
        $manager->method('get')->willReturnCallback(static fn ($class) => match ($class) {
            DataStore::class => $dataStore,
            PluginListInterface::class => $plugins,
            ComponentRegistry::class => $registry,
            ApplicationContainer::class => $container,
            EventBus::class => $eventBus,
            default => null
        });
        ObjectManager::setInstance($manager);
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(ObjectManager::class, '_instance'))->setValue(null, $this->previousObjectManager);
    }

    public static function emits(): iterable
    {
        $methods = [
            'emit' => ['pair'],
            'emitSelf' => ['pair'],
            'emitTo' => ['target', 'pair'],
            'emitToRefresh' => ['target'],
            'emitUp' => ['pair'],
            'emitToRefreshUp' => []
        ];
        foreach ($methods as $method => $prefix) {
            foreach ([
                'several' => [['left', 'right'], ['left', 'right']],
                'typed positional' => [[42, true], [42, true]],
                'array' => [[['left' => 'L', 'right' => 'R']], ['left' => 'L', 'right' => 'R']],
                'single' => [['value'], ['value']],
                'none' => [[], []],
                'null' => [[null], [null]],
                'null and another' => [[null, 'right'], [null, 'right']]
            ] as $case => [$arguments, $expected]) {
                yield "$method: $case" => [$method, [...$prefix, ...$arguments], $expected];
            }
        }
    }

    #[DataProvider('emits')]
    public function test_legacy_parameters_through_magento_interceptors(string $method, array $arguments, array $expected): void
    {
        $component = new EventComponent\Interceptor();
        store($component)->set('magewire:bc', true);
        $event = $component->{$method}(...$arguments);

        self::assertSame($expected, $event->serialize()['params']);
        $ancestors = in_array($method, ['emitUp', 'emitToRefreshUp'], true);
        self::assertSame([$event], store($component)->get($ancestors ? 'hyvaCheckout:ancestorEvents' : 'dispatched'));
        self::assertSame([], store($component)->get($ancestors ? 'dispatched' : 'hyvaCheckout:ancestorEvents', []));
        if ($method === 'emitSelf') {
            self::assertTrue($event->serialize()['self']);
        }
        if (in_array($method, ['emitTo', 'emitToRefresh'], true)) {
            self::assertSame('target', $event->serialize()['to']);
        }
    }

    public function test_variadic_emit_reaches_a_two_argument_listener(): void
    {
        $component = new EventComponent\Interceptor();
        store($component)->set('magewire:bc', true);
        $event = $component->emit('pair', 'left', 'right');

        self::assertSame(['left', 'right'], $component->onPair(...$event->serialize()['params']));
    }

    public function test_native_components_keep_the_core_emit_behavior(): void
    {
        $component = new EventComponent\Interceptor();

        self::assertSame(['left'], $component->emit('pair', 'left', 'right')->serialize()['params']);
        $event = $component->emitUp('pair', 'left', 'right');
        self::assertSame(['left'], $event->serialize()['params']);
        self::assertContains($event, store($component)->get('dispatched'));
        $refresh = $component->emitToRefreshUp(null);
        self::assertSame(['name' => '$refresh', 'params' => [null]], $refresh->serialize());
        self::assertContains($refresh, store($component)->get('dispatched'));
        self::assertSame([], store($component)->get('hyvaCheckout:ancestorEvents', []));
    }

    public function test_native_dispatch_is_unchanged_even_for_legacy_components(): void
    {
        $component = new EventComponent\Interceptor();
        store($component)->set('magewire:bc', true);
        $event = $component->dispatch('pair', left: 'left', right: 'right');

        self::assertSame(['left' => 'left', 'right' => 'right'], $event->serialize()['params']);
        self::assertSame([$event], store($component)->get('dispatched'));
        self::assertSame([], store($component)->get('hyvaCheckout:ancestorEvents', []));
    }

    public function test_mount_uses_the_checkout_container_before_dehydrate(): void
    {
        $this->withinCheckout = true;
        $component = new EventComponent\Interceptor();
        // The core resolver can initialize this to false before the Checkout fallback runs.
        store($component)->set('magewire:bc', false);

        self::assertSame(['left', 'right'], $component->emit('pair', 'left', 'right')->serialize()['params']);
    }

    public function test_explicit_opt_out_wins_through_generated_interceptors(): void
    {
        $this->withinCheckout = true;
        $component = new OptedOutComponent\Interceptor();
        store($component)->set('magewire:bc', true);

        self::assertSame(['left'], $component->emit('pair', 'left', 'right')->serialize()['params']);
        $component->emitUp('pair');
        self::assertSame([], store($component)->get('hyvaCheckout:ancestorEvents', []));
        self::assertFalse($this->backwardsCompatibility->isEnabled($component));
    }

    public function test_attribute_opt_in_works_outside_checkout(): void
    {
        $component = new OptedInComponent\Interceptor();

        self::assertSame(['left', 'right'], $component->emit('pair', 'left', 'right')->serialize()['params']);
    }

    public function test_hydrated_false_does_not_fall_back_to_the_container(): void
    {
        $this->withinCheckout = true;
        $component = new EventComponent\Interceptor();
        $this->feature($component)->hydrate(['bc' => ['enabled' => false]]);

        self::assertFalse($this->backwardsCompatibility->isEnabled($component));
    }

    public function test_compatibility_is_restored_before_component_hydrate_hooks_emit(): void
    {
        $component = new EventComponent\Interceptor();
        $bus = ObjectManager::getInstance()->get(EventBus::class);
        $bus->on('hydrate', static function (EventComponent $component): void {
            $component->emitUp('pair', 'left', 'right');
        });
        $this->feature($component)->provide();
        $bus->trigger('hydrate', $component, ['bc' => ['enabled' => true]]);

        $events = store($component)->get('hyvaCheckout:ancestorEvents', []);
        self::assertCount(1, $events);
        self::assertSame(['left', 'right'], $events[0]->serialize()['params']);
        self::assertSame([], store($component)->get('dispatched', []));
    }

    public function test_named_arguments_keep_the_core_calling_convention(): void
    {
        $component = new EventComponent\Interceptor();
        store($component)->set('magewire:bc', true);

        self::assertSame(['left' => 'L'], $component->emit(params: ['left' => 'L'], event: 'pair')->serialize()['params']);
        self::assertSame([null], $component->emitTo(params: null, event: 'pair', name: 'target')->serialize()['params']);
        self::assertSame([], $component->emitToRefreshUp()->serialize()['params']);
    }

    public function test_queued_event_keeps_scope_modifiers_until_serialization(): void
    {
        $component = new EventComponent\Interceptor();
        store($component)->set('magewire:bc', true);
        $event = $component->emitUp('pair', 'left', 'right')->self();
        $context = new ComponentContext($this->createStub(AbstractBlock::class), $component, true, new Effects(), new Memo());
        $this->feature($component)->dehydrate($context);

        self::assertSame([$event->serialize()], $context->getEffects()->getData('hyvaCheckoutAncestorEvents'));
        self::assertTrue($context->getEffects()->getData('hyvaCheckoutAncestorEvents')[0]['self']);
    }

    #[DataProvider('renderPhases')]
    public function test_ancestor_effect_is_emitted_on_mount_and_update(bool $mounting): void
    {
        $component = new EventComponent\Interceptor();
        store($component)->set('magewire:bc', true);
        $first = $component->emitUp('pair', 'left', 'right');
        $second = $component->emitToRefreshUp(null);
        $context = new ComponentContext($this->createStub(AbstractBlock::class), $component, $mounting, new Effects(), new Memo());
        $this->feature($component)->dehydrate($context);

        self::assertSame([$first->serialize(), $second->serialize()], $context->getEffects()->getData('hyvaCheckoutAncestorEvents'));
        self::assertTrue($context->getMemo()->getData('bc')['enabled']);
        self::assertSame([], store($component)->get('dispatched', []));
    }

    public static function renderPhases(): array
    {
        return ['mount' => [true], 'update' => [false]];
    }

    public function test_frontend_di_registers_the_plugin_on_component(): void
    {
        $di = simplexml_load_file(dirname(__DIR__, 2) . '/src/etc/frontend/di.xml');
        $plugins = $di->xpath('//type[@name="Magewirephp\\Magewire\\Component"]/plugin[@name="hyva_checkout_legacy_emit"]');

        self::assertCount(1, $plugins);
        self::assertSame(LegacyEmit::class, (string) $plugins[0]['type']);
    }

    private function feature(EventComponent $component): SupportHyvaCheckoutBackwardsCompatibility
    {
        $feature = new SupportHyvaCheckoutBackwardsCompatibility($this->backwardsCompatibility, new NullLogger(), new SupportEvents());
        $feature->setComponent($component);

        return $feature;
    }
}
