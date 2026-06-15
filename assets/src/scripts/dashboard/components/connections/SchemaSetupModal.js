/**
 * Schema Setup Modal Component
 * 
 * Single-step modal for Supabase schema setup:
 * - Display SQL code in scrollable area
 * - Copy to clipboard button
 * - Verify schema button
 * - Auto-close on success
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
    Modal,
    Button,
    Notice,
    Spinner,
    __experimentalText as Text,
    __experimentalVStack as VStack,
    __experimentalHStack as HStack
} from '@wordpress/components';
import { check, copy } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

const SchemaSetupModal = ({ 
    isOpen, 
    onRequestClose, 
    connectionName,
    dashboardUrl,
    onSuccess 
}) => {
    const [sqlContent, setSqlContent] = useState('');
    const [copied, setCopied] = useState(false);
    const [verifying, setVerifying] = useState(false);
    const [verifyResult, setVerifyResult] = useState(null);
    const [loading, setLoading] = useState(true);

    // Load SQL content when modal opens
    useEffect(() => {
        if (isOpen && !sqlContent) {
            loadSqlContent();
        }
    }, [isOpen]);

    const loadSqlContent = async () => {
        try {
            setLoading(true);
            const response = await apiFetch({
                path: `/gg-data/v1/schema/sql?connection=${connectionName}`
            });

            if (response.success) {
                setSqlContent(response.sql);
            }
        } catch (err) {
        } finally {
            setLoading(false);
        }
    };

    const handleCopySQL = async () => {
        try {
            if (navigator.clipboard) {
                await navigator.clipboard.writeText(sqlContent);
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = sqlContent;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
            }
            
            setCopied(true);
            setTimeout(() => setCopied(false), 3000);
        } catch (err) {
        }
    };

    const handleVerifySchema = async () => {
        try {
            setVerifying(true);
            setVerifyResult(null);
            const response = await apiFetch({
                path: `/gg-data/v1/schema/verify`,
                method: 'POST',
                data: {
                    connection: connectionName
                }
            });

            setVerifyResult(response);

            if (response.success && response.status === 'ready') {
                // Wait a moment to show success message, then close
                setTimeout(() => {
                    onSuccess();
                    onRequestClose();
                }, 1500);
            }
        } catch (err) {
            setVerifyResult({
                success: false,
                message: err.message || __('Verification failed', 'gregius-data')
            });
        } finally {
            setVerifying(false);
        }
    };

    const handleClose = () => {
        setCopied(false);
        setVerifyResult(null);
        onRequestClose();
    };

    if (!isOpen) return null;

    return (
        <Modal
            title={__('Setup Supabase Schema', 'gregius-data')}
            onRequestClose={handleClose}
            style={{ maxWidth: '800px' }}
            className="gg-schema-setup-modal"
        >
            <VStack spacing={4}>
                <Text>
                    {__('Paste and run the SQL code in the Supabase SQL Editor to create your database schema:', 'gregius-data')}
                </Text>

                {loading ? (
                    <div style={{ textAlign: 'center', padding: '40px' }}>
                        <Spinner />
                    </div>
                ) : (
                    <div style={{ position: 'relative' }}>
                        <pre style={{
                            background: '#f5f5f5',
                            border: '1px solid #ddd',
                            borderRadius: '4px',
                            padding: '16px',
                            maxHeight: '400px',
                            overflow: 'auto',
                            fontSize: '12px',
                            fontFamily: 'monospace',
                            lineHeight: '1.5'
                        }}>
                            {sqlContent}
                        </pre>
                    </div>
                )}

                {/* Verification result */}
                {verifyResult && (
                    verifyResult.success ? (
                        <Notice status="success" isDismissible={false}>
                            <strong>{__('Schema Verified!', 'gregius-data')}</strong>
                            {' '}
                            {__('Version', 'gregius-data')} <code>{verifyResult.version}</code> {__('is ready to use.', 'gregius-data')}
                        </Notice>
                    ) : (
                        <Notice status="warning" isDismissible={false}>
                            <strong>{__('Schema Not Found', 'gregius-data')}</strong>
                            <br />
                            {verifyResult.message || __('Please run the SQL in Supabase SQL Editor first.', 'gregius-data')}
                        </Notice>
                    )
                )}

                {/* Action buttons */}
                <HStack spacing={2} justify="flex-start">
                    <Button
                        variant="primary"
                        onClick={handleCopySQL}
                        icon={copied ? check : copy}
                        disabled={loading || verifying}
                    >
                        {copied ? __('Copied!', 'gregius-data') : __('Copy to Clipboard', 'gregius-data')}
                    </Button>
                    <Button
                        variant="secondary"
                        onClick={handleVerifySchema}
                        isBusy={verifying}
                        disabled={loading || verifying}
                    >
                        {verifying ? __('Verifying...', 'gregius-data') : __('Verify Schema', 'gregius-data')}
                    </Button>
                </HStack>
            </VStack>
        </Modal>
    );
};

export default SchemaSetupModal;
