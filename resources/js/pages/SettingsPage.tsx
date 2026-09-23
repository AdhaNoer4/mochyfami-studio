import React from 'react';
import { PageHeader } from '../components/ui/PageHeader';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '../components/ui/Card';
import { Badge } from '../components/ui/Badge';
import { Shield, Sliders, Database } from 'lucide-react';

export const SettingsPage: React.FC = () => {
  return (
    <div>
      <PageHeader
        title="Studio Settings"
        description="Configure application preferences, AI provider API keys, and system storage."
        badge={<Badge variant="slate">Configuration</Badge>}
      />

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Card variant="subtle">
          <CardHeader>
            <div className="flex items-center gap-3 mb-2">
              <div className="p-2 rounded-lg bg-indigo-500/10 text-indigo-400">
                <Shield className="w-5 h-5" />
              </div>
              <CardTitle>Authentication & Security</CardTitle>
            </div>
            <CardDescription>
              Sanctum Bearer Token management and user permissions.
            </CardDescription>
          </CardHeader>
          <CardContent className="text-xs text-slate-400">
            Active Provider: Sanctum API Auth
          </CardContent>
        </Card>

        <Card variant="subtle">
          <CardHeader>
            <div className="flex items-center gap-3 mb-2">
              <div className="p-2 rounded-lg bg-violet-500/10 text-violet-400">
                <Sliders className="w-5 h-5" />
              </div>
              <CardTitle>AI Provider Keys</CardTitle>
            </div>
            <CardDescription>
              OpenAI / Claude API key placeholders and rate limits.
            </CardDescription>
          </CardHeader>
          <CardContent className="text-xs text-slate-400">
            Status: Environment Configured
          </CardContent>
        </Card>

        <Card variant="subtle">
          <CardHeader>
            <div className="flex items-center gap-3 mb-2">
              <div className="p-2 rounded-lg bg-emerald-500/10 text-emerald-400">
                <Database className="w-5 h-5" />
              </div>
              <CardTitle>Database & Storage</CardTitle>
            </div>
            <CardDescription>
              MySQL connection & local asset disk configuration.
            </CardDescription>
          </CardHeader>
          <CardContent className="text-xs text-slate-400">
            Database: mochyfami_studio (MySQL 8.0)
          </CardContent>
        </Card>
      </div>
    </div>
  );
};
