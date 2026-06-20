<?php
declare(strict_types=1);

namespace Tests\Agent;

use PHPUnit\Framework\TestCase;

final class AgentPanelFilesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function test_agent_panel_index_exists(): void
    {
        $this->assertFileExists($this->root . '/agent-panel/index.php');
    }

    public function test_agent_api_scoped_file(): void
    {
        $src = file_get_contents($this->root . '/api/agent.php');
        $this->assertStringContainsString('Bearer', $src);
        $this->assertStringContainsString('id_user = ?', $src);
    }

    public function test_agent_bootstrap_csrf(): void
    {
        require_once $this->root . '/agent-panel/inc/bootstrap.php';
        $this->assertNotEmpty(agent_csrf_token());
    }

    public function test_agent_theme_allowed_list(): void
    {
        global $pdo;
        $theme = agent_panel_get_theme($pdo, '0');
        $allowed = ['navy', 'purple', 'emerald', 'sunset', 'slate', 'light', 'linen', 'mint', 'lavender', 'viranaut'];
        $this->assertContains($theme, $allowed);
    }

    public function test_agent_panel_assets(): void
    {
        $this->assertFileExists($this->root . '/agent-panel/assets/agent.js');
        $this->assertFileExists($this->root . '/agent-panel/assets/agent.css');
    }

    public function test_service_action_api_exists(): void
    {
        $this->assertFileExists($this->root . '/agent-panel/api/service_action.php');
    }

    public function test_dashboard_api_exists(): void
    {
        $this->assertFileExists($this->root . '/agent-panel/api/dashboard.php');
    }
}
