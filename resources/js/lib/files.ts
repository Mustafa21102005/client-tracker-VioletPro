export type FileKind = 'image' | 'pdf' | 'word' | 'other';
import type { AttachmentWithContext } from '@/types';
import { File as FileIcon, FileImage, FileText } from 'lucide-vue-next';
import type { Component } from 'vue';

const extensionKinds: Record<string, FileKind> = {
    pdf: 'pdf',
    doc: 'word',
    docx: 'word',
    jpg: 'image',
    jpeg: 'image',
    png: 'image',
    webp: 'image',
    gif: 'image',
};

export const MAX_IMAGE_SIZE = 1024 * 1024; // 1 MB
export const MAX_DOCUMENT_SIZE = 2 * 1024 * 1024; // 2 MB (PDF and Word)

const formatLimit = (bytes: number) => `${bytes / (1024 * 1024)} MB`;

export const fileLimitsHint = `PDF or Word up to ${formatLimit(MAX_DOCUMENT_SIZE)} · Images up to ${formatLimit(MAX_IMAGE_SIZE)}`;

// Returns a message when the file isn't allowed, or null when it is fine.
export function validateFile(file: File): string | null {
    if (!allowedExtensions.includes(fileExtension(file.name))) {
        return 'Only PDF, Word and image files (JPG, PNG, WEBP, GIF) are allowed.';
    }

    const isImage = fileKind(file.name) === 'image';
    const limit = isImage ? MAX_IMAGE_SIZE : MAX_DOCUMENT_SIZE;

    if (file.size > limit) {
        return `${isImage ? 'Images' : 'PDF and Word files'} can be at most ${formatLimit(limit)}. This one is ${formatFileSize(file.size)}.`;
    }

    return null;
}

export const allowedExtensions = Object.keys(extensionKinds);

export function fileExtension(fileName: string): string {
    return fileName.split('.').pop()?.toLowerCase() ?? '';
}

export function fileKind(fileName: string): FileKind {
    return extensionKinds[fileExtension(fileName)] ?? 'other';
}

export const fileKindClasses: Record<FileKind, string> = {
    pdf: 'bg-red-500/10 text-red-600 dark:text-red-400',
    word: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
    image: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    other: 'bg-muted text-muted-foreground',
};

export function formatFileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export const fileKindIcons: Record<FileKind, Component> = {
    pdf: FileText,
    word: FileText,
    image: FileImage,
    other: FileIcon,
};

// "Client · Company · Project" for an attachment's edit.
export function attachmentContext(attachment: AttachmentWithContext): string {
    const project = attachment.change_request?.project;

    return [project?.company?.client?.name, project?.company?.name, project?.name].filter(Boolean).join(' · ');
}
