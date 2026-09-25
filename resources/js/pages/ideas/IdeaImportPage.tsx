import React, { useState } from 'react';
import { PageHeader } from '../../components/ui/PageHeader';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '../../components/ui/Card';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { ideaService } from '../../services/ideaService';
import { ImportPreviewData, ImportResultData } from '../../types';
import {
  Upload,
  FileSpreadsheet,
  AlertTriangle,
  CheckCircle2,
  XCircle,
  ArrowLeft,
  Sparkles,
  AlertCircle,
  ArrowRight,
} from 'lucide-react';

interface IdeaImportPageProps {
  onNavigate: (path: string) => void;
}

export const IdeaImportPage: React.FC<IdeaImportPageProps> = ({ onNavigate }) => {
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [previewData, setPreviewData] = useState<ImportPreviewData | null>(null);
  const [importResult, setImportResult] = useState<ImportResultData | null>(null);

  const [loadingPreview, setLoadingPreview] = useState<boolean>(false);
  const [loadingImport, setLoadingImport] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      setSelectedFile(e.target.files[0]);
      setPreviewData(null);
      setImportResult(null);
      setError(null);
    }
  };

  const handlePreview = async () => {
    if (!selectedFile) return;
    setLoadingPreview(true);
    setError(null);
    try {
      const data = await ideaService.previewImportCsv(selectedFile);
      setPreviewData(data);
    } catch (err: any) {
      console.error('Failed to preview CSV:', err);
      setError(err.response?.data?.message || 'Failed to parse CSV file. Please verify file format.');
    } finally {
      setLoadingPreview(false);
    }
  };

  const handleExecuteImport = async () => {
    if (!previewData || previewData.valid_rows_count === 0) return;
    setLoadingImport(true);
    setError(null);
    try {
      const result = await ideaService.executeImport(previewData.rows);
      setImportResult(result);
    } catch (err: any) {
      console.error('Failed to execute import:', err);
      setError(err.response?.data?.message || 'Failed to complete bulk import. Transaction rolled back.');
    } finally {
      setLoadingImport(false);
    }
  };

  const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  };

  return (
    <div className="space-y-6 max-w-5xl mx-auto">
      <PageHeader
        title="Import Content Ideas"
        description="Bulk upload content topic ideas via CSV spreadsheet."
        action={
          <Button
            variant="outline"
            size="sm"
            icon={<ArrowLeft className="w-4 h-4" />}
            onClick={() => onNavigate('/ideas')}
          >
            Back to Ideas
          </Button>
        }
      />

      {/* ERROR ALERT */}
      {error && (
        <Card variant="subtle" className="border-rose-500/30 bg-rose-500/10 p-4">
          <div className="flex items-center gap-3 text-rose-300 text-xs">
            <AlertCircle className="w-5 h-5 text-rose-400 shrink-0" />
            <span>{error}</span>
          </div>
        </Card>
      )}

      {/* STEP 3: POST-IMPORT SUCCESS CONFIRMATION SCREEN */}
      {importResult ? (
        <Card variant="default" className="p-8 text-center space-y-6">
          <div className="w-16 h-16 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center mx-auto">
            <CheckCircle2 className="w-8 h-8" />
          </div>

          <div>
            <h3 className="text-xl font-bold text-white tracking-tight">Bulk Import Completed!</h3>
            <p className="text-xs text-slate-400 mt-1">
              Your content ideas have been processed and saved to the database.
            </p>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-lg mx-auto">
            <div className="p-4 rounded-xl bg-slate-900 border border-slate-800">
              <p className="text-2xl font-bold text-emerald-400">{importResult.imported_rows}</p>
              <p className="text-xs text-slate-400 font-medium">Ideas Imported</p>
            </div>
            <div className="p-4 rounded-xl bg-slate-900 border border-slate-800">
              <p className="text-2xl font-bold text-amber-400">{importResult.skipped_rows}</p>
              <p className="text-xs text-slate-400 font-medium">Rows Skipped</p>
            </div>
            <div className="p-4 rounded-xl bg-slate-900 border border-slate-800">
              <p className="text-2xl font-bold text-indigo-400">{importResult.duplicate_rows}</p>
              <p className="text-xs text-slate-400 font-medium">Duplicates Flagged</p>
            </div>
          </div>

          <div className="pt-4 flex justify-center">
            <Button
              variant="primary"
              size="md"
              icon={<ArrowRight className="w-4 h-4 text-white" />}
              onClick={() => onNavigate('/ideas')}
            >
              View Ideas Library
            </Button>
          </div>
        </Card>
      ) : (
        <>
          {/* STEP 1: FILE SELECTION BOX */}
          <Card variant="default">
            <CardHeader>
              <CardTitle>Upload CSV Spreadsheet</CardTitle>
              <CardDescription>
                Select a CSV file containing your topic ideas. Required columns: title, category, hook, concept, format.
              </CardDescription>
            </CardHeader>

            <CardContent className="space-y-4">
              <div className="border-2 border-dashed border-slate-800 rounded-xl p-8 text-center hover:border-indigo-500/50 transition-colors bg-slate-950/50">
                <input
                  type="file"
                  id="csv-upload"
                  accept=".csv,text/csv,text/plain"
                  onChange={handleFileChange}
                  className="hidden"
                />
                <label htmlFor="csv-upload" className="cursor-pointer flex flex-col items-center justify-center">
                  <div className="w-12 h-12 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center mb-3">
                    <FileSpreadsheet className="w-6 h-6" />
                  </div>
                  <p className="text-xs font-semibold text-slate-200">
                    {selectedFile ? selectedFile.name : 'Click to select CSV file'}
                  </p>
                  <p className="text-[11px] text-slate-500 mt-1">
                    {selectedFile
                      ? `Size: ${formatFileSize(selectedFile.size)}`
                      : 'Supported format: CSV (Max size 5MB, up to 500 rows)'}
                  </p>
                </label>
              </div>

              {/* Sample CSV format hint */}
              <div className="p-3 bg-slate-950/60 border border-slate-800/80 rounded-lg text-[11px] text-slate-400 font-mono">
                <span className="font-semibold text-indigo-400">Sample Header:</span> title,category,hook,concept,format,status,priority,notes
              </div>
            </CardContent>

            <CardFooter className="flex justify-end">
              <Button
                variant="primary"
                size="sm"
                disabled={!selectedFile}
                isLoading={loadingPreview}
                icon={<Upload className="w-4 h-4 text-white" />}
                onClick={handlePreview}
              >
                Preview Import
              </Button>
            </CardFooter>
          </Card>

          {/* STEP 2: PREVIEW SUMMARY & DETAILED DATA TABLE */}
          {previewData && (
            <div className="space-y-6">
              {/* Summary Metric Cards */}
              <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <Card variant="default" className="p-4">
                  <p className="text-xl font-bold text-white">{previewData.total_rows}</p>
                  <p className="text-xs text-slate-400">Total Rows</p>
                </Card>
                <Card variant="default" className="p-4">
                  <p className="text-xl font-bold text-emerald-400">{previewData.valid_rows_count}</p>
                  <p className="text-xs text-slate-400">Valid Rows</p>
                </Card>
                <Card variant="default" className="p-4">
                  <p className="text-xl font-bold text-rose-400">{previewData.invalid_rows_count}</p>
                  <p className="text-xs text-slate-400">Invalid Rows</p>
                </Card>
                <Card variant="default" className="p-4">
                  <p className="text-xl font-bold text-amber-400">{previewData.duplicate_rows_count}</p>
                  <p className="text-xs text-slate-400">Duplicates Flagged</p>
                </Card>
              </div>

              {/* Row Preview Table */}
              <Card variant="default">
                <CardHeader className="flex items-center justify-between">
                  <div>
                    <CardTitle>CSV Row Validation Preview</CardTitle>
                    <CardDescription>
                      Review row status before confirming import into database.
                    </CardDescription>
                  </div>
                </CardHeader>

                <CardContent className="p-0 overflow-x-auto">
                  <table className="w-full text-left border-collapse text-xs">
                    <thead>
                      <tr className="border-b border-slate-800/80 bg-slate-900/50 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                        <th className="py-3 px-4 w-12 text-center">#</th>
                        <th className="py-3 px-4">Title</th>
                        <th className="py-3 px-4">Category</th>
                        <th className="py-3 px-4">Format</th>
                        <th className="py-3 px-4 w-24">Status</th>
                        <th className="py-3 px-4">Validation Messages</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-800/60">
                      {previewData.rows.map((r) => (
                        <tr key={r.row_number} className="hover:bg-slate-800/30 transition-colors">
                          <td className="py-3 px-4 text-center font-mono text-slate-500">
                            {r.row_number}
                          </td>

                          <td className="py-3 px-4 font-medium text-slate-100 max-w-xs truncate">
                            {r.data.title || <span className="text-rose-400 italic">Empty</span>}
                          </td>

                          <td className="py-3 px-4 text-slate-300">
                            {r.data.category_name || <span className="text-rose-400 italic">Missing</span>}
                          </td>

                          <td className="py-3 px-4 text-slate-300 capitalize">{r.data.format}</td>

                          <td className="py-3 px-4">
                            {r.status === 'valid' && (
                              <Badge variant="emerald" size="sm">
                                <CheckCircle2 className="w-3 h-3 mr-1" /> Valid
                              </Badge>
                            )}
                            {r.status === 'invalid' && (
                              <Badge variant="rose" size="sm">
                                <XCircle className="w-3 h-3 mr-1" /> Invalid
                              </Badge>
                            )}
                            {r.status === 'duplicate' && (
                              <Badge variant="amber" size="sm">
                                <AlertTriangle className="w-3 h-3 mr-1" /> Duplicate
                              </Badge>
                            )}
                          </td>

                          <td className="py-3 px-4">
                            {r.errors.length > 0 ? (
                              <div className="text-rose-400 text-[11px] font-medium">
                                {r.errors.join(' ')}
                              </div>
                            ) : r.warnings.length > 0 ? (
                              <div className="text-amber-400 text-[11px] font-medium">
                                {r.warnings.join(' ')}
                              </div>
                            ) : (
                              <span className="text-emerald-400 text-[11px]">Ready to import</span>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </CardContent>

                <CardFooter className="flex items-center justify-between">
                  <Button variant="outline" size="sm" onClick={() => setPreviewData(null)}>
                    Cancel
                  </Button>

                  <Button
                    variant="primary"
                    size="sm"
                    disabled={previewData.valid_rows_count === 0}
                    isLoading={loadingImport}
                    icon={<Sparkles className="w-4 h-4 text-white" />}
                    onClick={handleExecuteImport}
                  >
                    Import {previewData.valid_rows_count} Valid Rows
                  </Button>
                </CardFooter>
              </Card>
            </div>
          )}
        </>
      )}
    </div>
  );
};
