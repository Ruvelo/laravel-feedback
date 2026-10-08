{{-- The Ruvelo design tokens as --feedback-* variables, scoped to $scope. Light, dark (following the system), and data-theme="light|dark" to pin one. --}}
    {{ $scope }} {
        --feedback-bg: #ffffff;
        --feedback-subtle: #f8f8fc;
        --feedback-muted: #f0f0f7;
        --feedback-line: #e4e4ef;
        --feedback-ink: #16162a;
        --feedback-text-2: #4b4b63;
        --feedback-text-3: #74748b;
        --feedback-accent: #3d4eff;
        --feedback-accent-2: #a78bfa;
        --feedback-accent-ink: #2b38d6;
        --feedback-accent-soft: #eef0ff;
        --feedback-on-accent: #ffffff;
        --feedback-lilac: #6b4fd8;
        --feedback-lilac-soft: #f3effe;
        --feedback-danger: #e5384f;
        --feedback-danger-ink: #c42639;
        --feedback-danger-soft: #fdecef;
        --feedback-success: #167a4a;
        --feedback-success-soft: #e8f8ef;
        --feedback-warning: #b26a00;
        --feedback-warning-soft: #fff4e0;
        --feedback-radius: 8px;
        --feedback-radius-lg: 14px;
        --feedback-sans: "Geist", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji";
        --feedback-mono: "Geist Mono", ui-monospace, "SF Mono", "Cascadia Code", Menlo, Consolas, monospace;
        color-scheme: light;
    }
    @media (prefers-color-scheme: dark) {
        {{ $scope }}:not([data-theme="light"]) {
            --feedback-bg: #11111c;
            --feedback-subtle: #171725;
            --feedback-muted: #1f1f30;
            --feedback-line: #2a2a3f;
            --feedback-ink: #f1f1f8;
            --feedback-text-2: #b6b6cc;
            --feedback-text-3: #8787a3;
            --feedback-accent: #8f9bff;
            --feedback-accent-2: #c4b5fd;
            --feedback-accent-ink: #b3bbff;
            --feedback-accent-soft: #1e2150;
            --feedback-on-accent: #0b0b1a;
            --feedback-lilac: #c4b5fd;
            --feedback-lilac-soft: #261f45;
            --feedback-danger: #ff6b80;
            --feedback-danger-ink: #ff8a9a;
            --feedback-danger-soft: #331520;
            --feedback-success: #74d6a2;
            --feedback-success-soft: #0f2b1f;
            --feedback-warning: #ffc266;
            --feedback-warning-soft: #2e2210;
            color-scheme: dark;
        }
    }
    {{ $scope }}[data-theme="dark"] {
        --feedback-bg: #11111c;
        --feedback-subtle: #171725;
        --feedback-muted: #1f1f30;
        --feedback-line: #2a2a3f;
        --feedback-ink: #f1f1f8;
        --feedback-text-2: #b6b6cc;
        --feedback-text-3: #8787a3;
        --feedback-accent: #8f9bff;
        --feedback-accent-2: #c4b5fd;
        --feedback-accent-ink: #b3bbff;
        --feedback-accent-soft: #1e2150;
        --feedback-on-accent: #0b0b1a;
        --feedback-lilac: #c4b5fd;
        --feedback-lilac-soft: #261f45;
        --feedback-danger: #ff6b80;
        --feedback-danger-ink: #ff8a9a;
        --feedback-danger-soft: #331520;
        --feedback-success: #74d6a2;
        --feedback-success-soft: #0f2b1f;
        --feedback-warning: #ffc266;
        --feedback-warning-soft: #2e2210;
        color-scheme: dark;
    }
    {{ $scope }} .fb-tone-accent { --fb-tone: var(--feedback-accent-ink); --fb-tone-soft: var(--feedback-accent-soft); --fb-tone-dot: var(--feedback-accent); }
    {{ $scope }} .fb-tone-lilac { --fb-tone: var(--feedback-lilac); --fb-tone-soft: var(--feedback-lilac-soft); --fb-tone-dot: var(--feedback-accent-2); }
    {{ $scope }} .fb-tone-success { --fb-tone: var(--feedback-success); --fb-tone-soft: var(--feedback-success-soft); --fb-tone-dot: var(--feedback-success); }
    {{ $scope }} .fb-tone-warning { --fb-tone: var(--feedback-warning); --fb-tone-soft: var(--feedback-warning-soft); --fb-tone-dot: var(--feedback-warning); }
    {{ $scope }} .fb-tone-danger { --fb-tone: var(--feedback-danger-ink); --fb-tone-soft: var(--feedback-danger-soft); --fb-tone-dot: var(--feedback-danger); }
    {{ $scope }} .fb-tone-neutral { --fb-tone: var(--feedback-text-2); --fb-tone-soft: var(--feedback-muted); --fb-tone-dot: var(--feedback-text-3); }
    {{ $scope }} .fb-tone-custom { --fb-tone-soft: color-mix(in srgb, var(--fb-tone) 14%, transparent); --fb-tone-dot: var(--fb-tone); }
