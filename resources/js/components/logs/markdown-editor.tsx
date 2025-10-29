import { useEffect, useState } from 'react';
import MDEditor from "@uiw/react-md-editor"

interface MarkdownEditorProps {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    minHeight?: string;
    maxHeight?: string;
}

export function MarkdownEditor({ value, onChange, placeholder = 'Write something...', minHeight = '200px', maxHeight = '500px' }: MarkdownEditorProps) {
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        setMounted(true);
    }, []);

    if (!mounted) {
        return (
            <div 
                className="flex items-center justify-center rounded-md border border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900"
                style={{ minHeight }}
            >
                <span className="text-sm text-gray-500">Loading editor...</span>
            </div>
        );
    }

    return (
        <div className="markdown-editor-wrapper" data-color-mode="light">
            <MDEditor
                value={value}
                onChange={(val) => onChange(val || '')}
                preview="edit"
                height={minHeight}
                textareaProps={{
                    placeholder: placeholder,
                }}
                previewOptions={{
                    rehypePlugins: [],
                }}
                commandsFilter={(cmd) => {
                    // Remove fullscreen and preview buttons as they can cause issues
                    if (cmd.name === 'fullscreen' || cmd.name === 'preview') {
                        return false;
                    }
                    return true;
                }}
            />
            <style jsx global>{`
                .markdown-editor-wrapper .w-md-editor {
                    box-shadow: none !important;
                    border-radius: 0.375rem;
                }
                
                .markdown-editor-wrapper .w-md-editor-toolbar {
                    background-color: rgb(249 250 251);
                    border-bottom: 1px solid rgb(229 231 235);
                    padding: 8px;
                }
                
                .markdown-editor-wrapper .w-md-editor-content {
                    background-color: white;
                }
                
                .markdown-editor-wrapper .w-md-editor-text-pre,
                .markdown-editor-wrapper .w-md-editor-text-input {
                    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace !important;
                    font-size: 14px !important;
                    line-height: 1.6 !important;
                }
                
                .markdown-editor-wrapper .w-md-editor-text-pre {
                    padding: 12px !important;
                }
                
                .markdown-editor-wrapper .w-md-editor-text-input {
                    padding: 12px !important;
                }
                
                .markdown-editor-wrapper .w-md-editor-toolbar button {
                    color: rgb(75 85 99);
                }
                
                .markdown-editor-wrapper .w-md-editor-toolbar button:hover {
                    background-color: rgb(243 244 246);
                    color: rgb(17 24 39);
                }
                
                .markdown-editor-wrapper .w-md-editor-toolbar button.active {
                    background-color: rgb(219 234 254);
                    color: rgb(30 64 175);
                }
                
                /* Dark mode styles */
                .dark .markdown-editor-wrapper .w-md-editor {
                    background-color: rgb(17 24 39);
                    border-color: rgb(55 65 81);
                }
                
                .dark .markdown-editor-wrapper .w-md-editor-toolbar {
                    background-color: rgb(31 41 55);
                    border-bottom-color: rgb(55 65 81);
                }
                
                .dark .markdown-editor-wrapper .w-md-editor-content {
                    background-color: rgb(17 24 39);
                }
                
                .dark .markdown-editor-wrapper .w-md-editor-text-pre,
                .dark .markdown-editor-wrapper .w-md-editor-text-input {
                    color: rgb(229 231 235);
                }
                
                .dark .markdown-editor-wrapper .w-md-editor-toolbar button {
                    color: rgb(156 163 175);
                }
                
                .dark .markdown-editor-wrapper .w-md-editor-toolbar button:hover {
                    background-color: rgb(55 65 81);
                    color: rgb(229 231 235);
                }
                
                .dark .markdown-editor-wrapper .w-md-editor-toolbar button.active {
                    background-color: rgb(30 64 175);
                    color: rgb(219 234 254);
                }
                
                /* Preview mode styles */
                .markdown-editor-wrapper .wmde-markdown {
                    font-family: inherit;
                    font-size: 14px;
                    line-height: 1.6;
                    color: rgb(17 24 39);
                    background-color: white;
                    padding: 12px;
                }
                
                .dark .markdown-editor-wrapper .wmde-markdown {
                    color: rgb(229 231 235);
                    background-color: rgb(17 24 39);
                }
                
                .markdown-editor-wrapper .wmde-markdown h1,
                .markdown-editor-wrapper .wmde-markdown h2,
                .markdown-editor-wrapper .wmde-markdown h3,
                .markdown-editor-wrapper .wmde-markdown h4,
                .markdown-editor-wrapper .wmde-markdown h5,
                .markdown-editor-wrapper .wmde-markdown h6 {
                    font-weight: 600;
                    margin-top: 1.5em;
                    margin-bottom: 0.5em;
                }
                
                .markdown-editor-wrapper .wmde-markdown ul,
                .markdown-editor-wrapper .wmde-markdown ol {
                    padding-left: 1.5em;
                    margin-bottom: 1em;
                }
                
                .markdown-editor-wrapper .wmde-markdown code {
                    background-color: rgb(243 244 246);
                    padding: 0.125rem 0.25rem;
                    border-radius: 0.25rem;
                    font-size: 0.875em;
                }
                
                .dark .markdown-editor-wrapper .wmde-markdown code {
                    background-color: rgb(31 41 55);
                }
                
                .markdown-editor-wrapper .wmde-markdown pre {
                    background-color: rgb(243 244 246);
                    padding: 1rem;
                    border-radius: 0.375rem;
                    overflow-x: auto;
                    margin-bottom: 1rem;
                }
                
                .dark .markdown-editor-wrapper .wmde-markdown pre {
                    background-color: rgb(31 41 55);
                }
                
                .markdown-editor-wrapper .wmde-markdown pre code {
                    background-color: transparent;
                    padding: 0;
                }
                
                .markdown-editor-wrapper .wmde-markdown a {
                    color: rgb(37 99 235);
                    text-decoration: underline;
                }
                
                .dark .markdown-editor-wrapper .wmde-markdown a {
                    color: rgb(96 165 250);
                }
                
                .markdown-editor-wrapper .wmde-markdown blockquote {
                    border-left: 4px solid rgb(229 231 235);
                    padding-left: 1rem;
                    color: rgb(107 114 128);
                    font-style: italic;
                    margin: 1.5em 0;
                }
                
                .dark .markdown-editor-wrapper .wmde-markdown blockquote {
                    border-left-color: rgb(55 65 81);
                    color: rgb(156 163 175);
                }
                
                .markdown-editor-wrapper .wmde-markdown img {
                    max-width: 100%;
                    border-radius: 0.375rem;
                    margin: 1rem 0;
                }
            `}</style>
        </div>
    );
}