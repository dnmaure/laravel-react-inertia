import React, { useState, useRef } from 'react';
import { Button } from '@/Components/ui/ui/button';
import { Progress } from '@/Components/ui/ui/progress';
import { Input } from '@/Components/ui/ui/input';
import { Label } from '@/Components/ui/ui/label';
import { X, Upload, File, Video, Image } from 'lucide-react';

export default function FileUploadWithProgress({ 
    name, 
    label, 
    value, 
    onChange, 
    accept = "*",
    maxSize = 10240, // 10MB default
    className = "",
    multiple = false,
    showPreview = true 
}) {
    const [uploadProgress, setUploadProgress] = useState(0);
    const [uploading, setUploading] = useState(false);
    const [preview, setPreview] = useState(null);
    const [error, setError] = useState('');
    const fileInputRef = useRef(null);

    const getFileIcon = (fileType) => {
        if (fileType?.startsWith('video/')) return <Video className="h-8 w-8 text-blue-500" />;
        if (fileType?.startsWith('image/')) return <Image className="h-8 w-8 text-green-500" />;
        return <File className="h-8 w-8 text-gray-500" />;
    };

    const formatFileSize = (bytes) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const validateFile = (file) => {
        const maxSizeBytes = maxSize * 1024; // Convert KB to bytes
        
        if (file.size > maxSizeBytes) {
            return `File size must be less than ${formatFileSize(maxSizeBytes)}`;
        }

        // Additional validations based on accept type
        if (accept !== "*" && !file.type.match(accept.replace(/\*/g, '.*'))) {
            return `File type not allowed. Accepted types: ${accept}`;
        }

        return null;
    };

    const handleFileSelect = async (event) => {
        const files = Array.from(event.target.files || []);
        if (files.length === 0) return;

        const file = files[0]; // Handle single file for now
        const validationError = validateFile(file);
        
        if (validationError) {
            setError(validationError);
            return;
        }

        setError('');
        setUploading(true);
        setUploadProgress(0);

        try {
            // Simulate upload progress for better UX
            const progressInterval = setInterval(() => {
                setUploadProgress(prev => {
                    if (prev >= 90) {
                        clearInterval(progressInterval);
                        return prev;
                    }
                    return prev + Math.random() * 15;
                });
            }, 200);

            // Create preview if it's an image or video
            if (showPreview && (file.type.startsWith('image/') || file.type.startsWith('video/'))) {
                const reader = new FileReader();
                reader.onload = (e) => setPreview({ url: e.target.result, type: file.type });
                reader.readAsDataURL(file);
            }

            // Call onChange with the file
            onChange(file);

            // Complete progress
            setTimeout(() => {
                clearInterval(progressInterval);
                setUploadProgress(100);
                setUploading(false);
            }, 1000);

        } catch (err) {
            setError('Upload failed. Please try again.');
            setUploading(false);
            setUploadProgress(0);
        }
    };

    const handleRemove = () => {
        setPreview(null);
        setUploadProgress(0);
        setUploading(false);
        setError('');
        onChange(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    return (
        <div className={`space-y-4 ${className}`}>
            {label && <Label htmlFor={name}>{label}</Label>}
            
            {/* Upload Area */}
            <div className="relative">
                <Input
                    ref={fileInputRef}
                    id={name}
                    name={name}
                    type="file"
                    accept={accept}
                    multiple={multiple}
                    onChange={handleFileSelect}
                    className="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                    disabled={uploading}
                />
                
                <div className={`
                    border-2 border-dashed rounded-lg p-6 text-center transition-colors
                    ${uploading ? 'border-blue-300 bg-blue-50' : 'border-gray-300 hover:border-gray-400'}
                    ${error ? 'border-red-300 bg-red-50' : ''}
                `}>
                    <Upload className="mx-auto h-12 w-12 text-gray-400 mb-4" />
                    <p className="text-sm text-gray-600 mb-2">
                        {uploading ? 'Uploading...' : 'Click to upload or drag and drop'}
                    </p>
                    <p className="text-xs text-gray-500">
                        Max size: {formatFileSize(maxSize * 1024)}
                        {accept !== "*" && ` • Types: ${accept}`}
                    </p>
                </div>
            </div>

            {/* Progress Bar */}
            {uploading && (
                <div className="space-y-2">
                    <div className="flex justify-between text-sm">
                        <span>Uploading...</span>
                        <span>{Math.round(uploadProgress)}%</span>
                    </div>
                    <Progress value={uploadProgress} className="w-full" />
                </div>
            )}

            {/* Error Message */}
            {error && (
                <div className="text-sm text-red-600 bg-red-50 p-2 rounded">
                    {error}
                </div>
            )}

            {/* Preview */}
            {preview && !uploading && (
                <div className="relative bg-gray-50 rounded-lg p-4">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={handleRemove}
                        className="absolute top-2 right-2 h-6 w-6 p-0"
                    >
                        <X className="h-4 w-4" />
                    </Button>

                    <div className="flex items-center space-x-3">
                        {getFileIcon(preview.type)}
                        <div className="flex-1 min-w-0">
                            <p className="text-sm font-medium text-gray-900 truncate">
                                {value?.name || 'Uploaded file'}
                            </p>
                            <p className="text-sm text-gray-500">
                                {value?.size ? formatFileSize(value.size) : ''}
                            </p>
                        </div>
                    </div>

                    {/* Image Preview */}
                    {preview.type.startsWith('image/') && (
                        <div className="mt-3">
                            <img 
                                src={preview.url} 
                                alt="Preview" 
                                className="max-w-full h-32 object-cover rounded"
                            />
                        </div>
                    )}

                    {/* Video Preview */}
                    {preview.type.startsWith('video/') && (
                        <div className="mt-3">
                            <video 
                                src={preview.url} 
                                controls 
                                className="max-w-full h-32 rounded"
                            />
                        </div>
                    )}
                </div>
            )}

            {/* Current Value Display (for existing files) */}
            {value && typeof value === 'string' && !preview && (
                <div className="bg-gray-50 rounded-lg p-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-3">
                            <File className="h-8 w-8 text-gray-500" />
                            <div>
                                <p className="text-sm font-medium text-gray-900">
                                    Current file: {value}
                                </p>
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={handleRemove}
                        >
                            <X className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
