<?php

namespace Tests\Unit;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Pecotamic\Antispam\InstallNotice;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class InstallNoticeTest extends TestCase
{
    private BufferedOutput $output;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('pecotamic.antispam.rules.pixel.weight', 60);
        config()->set('pecotamic.antispam.rules.interaction.weight', 60);
    }

    /**
     * With automatic placement on there is nothing to install, so there is
     * nothing to say.
     */
    public function test_it_stays_quiet_while_placement_is_automatic(): void
    {
        $this->withViews('<form>{{ fields }}</form>');

        $this->assertSame('', $this->notice());
    }

    public function test_it_speaks_up_when_placement_is_off_and_no_tag_is_used(): void
    {
        config()->set('pecotamic.antispam.inject', false);
        $this->withViews('<form>{{ fields }}</form>');

        $notice = $this->notice();

        $this->assertStringContainsString('{{ antispam }}', $notice);
        $this->assertStringContainsString('nothing to judge', $notice);
    }

    public function test_it_stays_quiet_when_placement_is_off_but_the_tag_is_used(): void
    {
        config()->set('pecotamic.antispam.inject', false);
        $this->withViews('<form>{{ antispam }}{{ fields }}</form>');

        $this->assertSame('', $this->notice());
    }

    /**
     * Nothing to warn about when the rules that need the tag are switched off.
     */
    public function test_it_stays_quiet_when_the_proof_rules_are_off(): void
    {
        config()->set('pecotamic.antispam.inject', false);
        config()->set('pecotamic.antispam.rules.pixel.weight', 0);
        config()->set('pecotamic.antispam.rules.interaction.weight', 0);
        $this->withViews('<form>{{ fields }}</form>');

        $this->assertSame('', $this->notice());
    }

    public function test_it_reports_a_published_config_from_before_the_rules_key(): void
    {
        $this->withPublishedConfig("<?php return ['patterns' => [], 'minimum_fill_time' => 5];");

        $notice = $this->notice();

        $this->assertStringContainsString('before 0.2', $notice);
        $this->assertStringContainsString('patterns', $notice);
        $this->assertStringContainsString('minimum_fill_time', $notice);
    }

    public function test_it_accepts_a_current_published_config(): void
    {
        $this->withPublishedConfig("<?php return ['threshold' => 100, 'rules' => []];");

        $this->assertSame('', $this->notice());
    }

    private function notice(): string
    {
        $this->output = new BufferedOutput;

        $command = new Command;
        $command->setOutput(new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]), $this->output
        ));

        app(InstallNotice::class)->printTo($command);

        return trim($this->output->fetch());
    }

    private function withViews(string $contents): void
    {
        File::ensureDirectoryExists($path = resource_path('views'));
        File::put($path.'/form.antlers.html', $contents);
    }

    private function withPublishedConfig(string $contents): void
    {
        File::ensureDirectoryExists($path = config_path('pecotamic'));
        File::put($path.'/antispam.php', $contents);
    }

    protected function tearDown(): void
    {
        File::delete(resource_path('views/form.antlers.html'));
        File::deleteDirectory(config_path('pecotamic'));

        parent::tearDown();
    }
}
