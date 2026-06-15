/**
 * Connection Form Component
 * 
 * Form for creating and editing PostgreSQL database connections.
 * Includes validation, field types, and security considerations.
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
    TextControl,
    NumberControl,
    SelectControl,
    TextareaControl,
    ToggleControl,
    Button,
    Notice
} from '@wordpress/components';

// Create fallback NumberControl if not available
const SafeNumberControl = NumberControl || (({ label, value, onChange, min, max, help, ...props }) => {
    return (
        <TextControl
            label={label}
            value={value?.toString() || ''}
            onChange={(newValue) => {
                const numValue = parseInt(newValue, 10);
                if (!isNaN(numValue)) {
                    onChange(numValue);
                }
            }}
            type="number"
            min={min}
            max={max}
            help={help}
            __next40pxDefaultSize={true}
            __nextHasNoMarginBottom={true}
            {...props}
        />
    );
});

const ConnectionForm = ({
    initialData = {},
    onSubmit,
    onCancel,
    submitLabel = __('Save Connection', 'gregius-data'),
    isEdit = false
}) => {
    // Form state
    const [formData, setFormData] = useState({
        name: '',
        type: 'postgresql',
        host: 'localhost',
        port: 5432,
        database: '',
        username: '',
        password: '',
        ssl_mode: 'prefer',
        connect_timeout: 30,
        description: '',
        is_active: true,
        publishable_key: '',
        secret_key: '',
        ...initialData,
        // In edit mode, clear masked password to prevent sending it back
        ...(isEdit && initialData.password === '***' ? { password: '' } : {})
    });

    const [errors, setErrors] = useState({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Database type options (Phase R.2.4)
    const databaseTypeOptions = [
        { value: 'postgresql', label: __('PostgreSQL (Direct)', 'gregius-data') },
        { value: 'postgrest', label: __('Supabase (REST)', 'gregius-data') }
    ];

    // SSL mode options
    const sslModeOptions = [
        { value: 'disable', label: __('Disable - No SSL', 'gregius-data') },
        { value: 'allow', label: __('Allow - SSL if available', 'gregius-data') },
        { value: 'prefer', label: __('Prefer - SSL preferred (default)', 'gregius-data') },
        { value: 'require', label: __('Require - SSL required', 'gregius-data') },
        { value: 'verify-ca', label: __('Verify CA - Verify certificate authority', 'gregius-data') },
        { value: 'verify-full', label: __('Verify Full - Full certificate verification', 'gregius-data') }
    ];

    // Update form data when initialData changes
    useEffect(() => {
        if (initialData && Object.keys(initialData).length > 0) {
            const normalizedInitialData = {
                ...initialData,
                publishable_key: initialData.publishable_key || initialData.api_key || '',
                secret_key: initialData.secret_key || initialData.service_role_key || ''
            };

            setFormData(prev => ({
                ...prev,
                ...normalizedInitialData,
                // In edit mode, clear masked password to prevent sending it back
                ...(isEdit && initialData.password === '***' ? { password: '' } : {})
            }));
        }
    }, [initialData, isEdit]);

    // Validation function
    const validateForm = () => {
        const newErrors = {};

        // Name validation
        if (!formData.name || formData.name.trim() === '') {
            newErrors.name = __('Connection name is required', 'gregius-data');
        } else if (!/^[a-zA-Z0-9_-]+$/.test(formData.name)) {
            newErrors.name = __('Connection name can only contain letters, numbers, hyphens, and underscores', 'gregius-data');
        }

        // Type-specific validation
        if (formData.type === 'postgrest') {
            // PostgREST validation (Supabase, Neon, etc.)
            if (!formData.project_url || formData.project_url.trim() === '') {
                newErrors.project_url = __('Project URL is required', 'gregius-data');
            } else if (!/^https:\/\/.+\.supabase\.co$/.test(formData.project_url.trim())) {
                newErrors.project_url = __('Invalid Supabase URL format (should be https://xxx.supabase.co)', 'gregius-data');
            }

            if (!formData.publishable_key || formData.publishable_key.trim() === '') {
                newErrors.publishable_key = __('Publishable Key is required', 'gregius-data');
            }

            if (!formData.secret_key || formData.secret_key.trim() === '') {
                newErrors.secret_key = __('Secret Key is required', 'gregius-data');
            }
        } else {
            // Direct PostgreSQL validation
            if (!formData.host || formData.host.trim() === '') {
                newErrors.host = __('Host is required', 'gregius-data');
            }

            if (!formData.port || formData.port < 1 || formData.port > 65535) {
                newErrors.port = __('Port must be between 1 and 65535', 'gregius-data');
            }

            if (!formData.database || formData.database.trim() === '') {
                newErrors.database = __('Database name is required', 'gregius-data');
            }

            if (!formData.username || formData.username.trim() === '') {
                newErrors.username = __('Username is required', 'gregius-data');
            }

            // Password validation (only for new connections)
            if (!isEdit && (!formData.password || formData.password.trim() === '')) {
                newErrors.password = __('Password is required', 'gregius-data');
            }
        }

        // Timeout validation (applies to all types)
        if (!formData.connect_timeout || formData.connect_timeout < 1 || formData.connect_timeout > 300) {
            newErrors.connect_timeout = __('Timeout must be between 1 and 300 seconds', 'gregius-data');
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    // Handle form submission
    const handleSubmit = async (event) => {
        event.preventDefault();

        if (!validateForm()) {
            return;
        }

        setIsSubmitting(true);

        try {
            // Prepare submission data - only include fields relevant to provider type
            const submitData = {
                name: formData.name,
                description: formData.description,
                type: formData.type,
                connect_timeout: formData.connect_timeout,
                is_active: formData.is_active
            };

            // Add provider-specific fields
            if (formData.type === 'postgrest') {
                submitData.project_url = formData.project_url;
                // Only include API keys if they were changed (not masked)
                if (formData.publishable_key && formData.publishable_key !== '***') {
                    submitData.publishable_key = formData.publishable_key;
                }
                if (formData.secret_key && formData.secret_key !== '***') {
                    submitData.secret_key = formData.secret_key;
                }
                // Include access_token if provided (stored for future Edge Functions deployment)
                if (formData.access_token && formData.access_token !== '***') {
                    submitData.access_token = formData.access_token;
                }
            } else {
                // PostgreSQL fields
                submitData.host = formData.host;
                submitData.port = formData.port;
                submitData.database = formData.database;
                submitData.username = formData.username;
                submitData.ssl_mode = formData.ssl_mode;
                
                // Only include password if it was changed (not empty in edit mode)
                if (!isEdit || (formData.password && formData.password.trim() !== '')) {
                    submitData.password = formData.password;
                }
            }
            
            await onSubmit(submitData);
        } catch (error) {
            setErrors({ submit: error.message || __('An error occurred while saving', 'gregius-data') });
        } finally {
            setIsSubmitting(false);
        }
    };

    // Handle field changes
    const handleFieldChange = (field, value) => {
        // If changing database type, clear provider-specific fields
        if (field === 'type') {
            const newData = {
                name: formData.name,
                description: formData.description,
                type: value,
                connect_timeout: formData.connect_timeout || 30,
                is_active: formData.is_active
            };

            // Add default fields based on new type
            if (value === 'postgrest') {
                newData.project_url = '';
                newData.publishable_key = '';
                newData.secret_key = '';
                newData.access_token = '';
            } else {
                newData.host = 'localhost';
                newData.port = 5432;
                newData.database = '';
                newData.username = '';
                newData.password = '';
                newData.ssl_mode = 'prefer';
            }

            setFormData(newData);
            setErrors({}); // Clear all errors
            return;
        }

        setFormData(prev => ({
            ...prev,
            [field]: value
        }));

        // Clear field error when user starts typing
        if (errors[field]) {
            setErrors(prev => ({
                ...prev,
                [field]: undefined
            }));
        }
    };

    return (
        <form
            onSubmit={handleSubmit}
            className="gg-data-connection-form"
        >
            <div
                className="gg-data-form-fields"
                style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}
            >
                {/* Submit error notice */}
                {errors.submit && (
                    <Notice
                        status="error"
                        isDismissible={false}
                    >
                        {errors.submit}
                    </Notice>
                )}

                {/* Connection name */}
                <TextControl
                    label={__('Connection Name', 'gregius-data')}
                    value={formData.name}
                    onChange={(value) => handleFieldChange('name', value)}
                    error={errors.name}
                    help={__('Unique identifier for this connection (letters, numbers, hyphens, underscores only)', 'gregius-data')}
                    disabled={isEdit}
                    __next40pxDefaultSize={true}
                    __nextHasNoMarginBottom={true}
                />

                {/* Database type (Phase R.2.4) */}
                <SelectControl
                    label={__('Database Type', 'gregius-data')}
                    value={formData.type}
                    onChange={(value) => handleFieldChange('type', value)}
                    options={databaseTypeOptions}
                    help={isEdit 
                        ? __('⚠️ Changing provider type will require re-entering all connection details.', 'gregius-data')
                        : __('Select the database provider. Supabase works on any WordPress hosting without PHP extensions.', 'gregius-data')
                    }
                    __next40pxDefaultSize={true}
                    __nextHasNoMarginBottom={true}
                />

                {/* Description */}
                <TextareaControl
                    label={__('Description (Optional)', 'gregius-data')}
                    value={formData.description}
                    onChange={(value) => handleFieldChange('description', value)}
                    help={__('Brief description of this connection', 'gregius-data')}
                    rows={2}
                    __nextHasNoMarginBottom={true}
                />

                {/* Connection details section - Conditional based on type */}
                {formData.type === 'postgrest' ? (
                    <fieldset style={{ border: '1px solid #ddd', padding: '16px', borderRadius: '4px' }}>
                        <legend style={{ fontWeight: 'bold', padding: '0 8px' }}>
                            {__('Supabase Project Details', 'gregius-data')}
                        </legend>

                        <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                            {/* Project URL */}
                            <TextControl
                                label={__('Project URL', 'gregius-data')}
                                value={formData.project_url || ''}
                                onChange={(value) => handleFieldChange('project_url', value)}
                                error={errors.project_url}
                                placeholder="https://abcdefgh.supabase.co"
                                help={__('Your Supabase project URL (Settings → API → Project URL)', 'gregius-data')}
                                __next40pxDefaultSize={true}
                                __nextHasNoMarginBottom={true}
                            />
                        </div>
                    </fieldset>
                ) : (
                    <fieldset style={{ border: '1px solid #ddd', padding: '16px', borderRadius: '4px' }}>
                        <legend style={{ fontWeight: 'bold', padding: '0 8px' }}>
                            {__('Connection Details', 'gregius-data')}
                        </legend>

                        <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                            {/* Host and Port in same row */}
                            <div style={{ display: 'flex', gap: '12px', alignItems: 'end' }}>
                                <div style={{ flex: '1' }}>
                                    <TextControl
                                        label={__('Host', 'gregius-data')}
                                        value={formData.host}
                                        onChange={(value) => handleFieldChange('host', value)}
                                        error={errors.host}
                                        placeholder="localhost"
                                        __next40pxDefaultSize={true}
                                        __nextHasNoMarginBottom={true}
                                    />
                                </div>
                                <div style={{ width: '120px' }}>
                                    <SafeNumberControl
                                        label={__('Port', 'gregius-data')}
                                        value={formData.port}
                                        onChange={(value) => handleFieldChange('port', parseInt(value) || 5432)}
                                        error={errors.port}
                                        min={1}
                                        max={65535}
                                    />
                                </div>
                            </div>

                            {/* Database name */}
                            <TextControl
                                label={__('Database Name', 'gregius-data')}
                                value={formData.database}
                                onChange={(value) => handleFieldChange('database', value)}
                                error={errors.database}
                                placeholder="my_database"
                                __next40pxDefaultSize={true}
                                __nextHasNoMarginBottom={true}
                            />
                        </div>
                    </fieldset>
                )}

                {/* Authentication section - Conditional based on type */}
                <fieldset style={{ border: '1px solid #ddd', padding: '16px', borderRadius: '4px' }}>
                    <legend style={{ fontWeight: 'bold', padding: '0 8px' }}>
                        {formData.type === 'postgrest' ? __('API Keys', 'gregius-data') : __('Authentication', 'gregius-data')}
                    </legend>

                    {formData.type === 'postgrest' ? (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                            {/* Anon API Key */}
                            <TextControl
                                label={__('Publishable Key', 'gregius-data')}
                                type="password"
                                value={formData.publishable_key || ''}
                                onChange={(value) => handleFieldChange('publishable_key', value)}
                                error={errors.publishable_key}
                                help={__('Your publishable key (Settings → API Keys → Publishable and secret API keys)', 'gregius-data')}
                                placeholder="sb_publishable_..."
                                __next40pxDefaultSize={true}
                                __nextHasNoMarginBottom={true}
                            />

                            {/* Service Role Key */}
                            <TextControl
                                label={__('Secret Key', 'gregius-data')}
                                type="password"
                                value={formData.secret_key || ''}
                                onChange={(value) => handleFieldChange('secret_key', value)}
                                error={errors.secret_key}
                                help={__('Your secret key (Settings → API Keys → Publishable and secret API keys). Keep this secure!', 'gregius-data')}
                                placeholder="sb_secret_..."
                                __next40pxDefaultSize={true}
                                __nextHasNoMarginBottom={true}
                            />
                        </div>
                    ) : (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                            {/* Username */}
                            <TextControl
                                label={__('Username', 'gregius-data')}
                                value={formData.username}
                                onChange={(value) => handleFieldChange('username', value)}
                                error={errors.username}
                                placeholder="database_user"
                                __next40pxDefaultSize={true}
                                __nextHasNoMarginBottom={true}
                            />

                            {/* Password */}
                            <TextControl
                                label={__('Password', 'gregius-data')}
                                type="password"
                                value={formData.password}
                                onChange={(value) => handleFieldChange('password', value)}
                                error={errors.password}
                                help={isEdit ? __('Leave blank to keep current password', 'gregius-data') : undefined}
                                placeholder={isEdit ? __('(current password)', 'gregius-data') : ''}
                                __next40pxDefaultSize={true}
                                __nextHasNoMarginBottom={true}
                            />
                        </div>
                    )}
                </fieldset>

                {/* Advanced settings section */}
                <fieldset style={{ border: '1px solid #ddd', padding: '16px', borderRadius: '4px' }}>
                    <legend style={{ fontWeight: 'bold', padding: '0 8px' }}>
                        {__('Advanced Settings', 'gregius-data')}
                    </legend>

                    <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                        {/* SSL Mode */}
                        <SelectControl
                            label={__('SSL Mode', 'gregius-data')}
                            value={formData.ssl_mode}
                            onChange={(value) => handleFieldChange('ssl_mode', value)}
                            options={sslModeOptions}
                            help={__('SSL encryption settings for the connection', 'gregius-data')}
                            __next40pxDefaultSize={true}
                            __nextHasNoMarginBottom={true}
                        />

                        {/* Connection timeout */}
                        <SafeNumberControl
                            label={__('Connection Timeout (seconds)', 'gregius-data')}
                            value={formData.connect_timeout}
                            onChange={(value) => handleFieldChange('connect_timeout', parseInt(value) || 30)}
                            error={errors.connect_timeout}
                            min={1}
                            max={300}
                            help={__('Maximum time to wait for connection', 'gregius-data')}
                        />

                        {/* Active toggle */}
                        <ToggleControl
                            label={__('Active Connection', 'gregius-data')}
                            checked={formData.is_active}
                            onChange={(value) => handleFieldChange('is_active', value)}
                            help={__('Enable or disable this connection', 'gregius-data')}
                            __nextHasNoMarginBottom={true}
                        />
                    </div>
                </fieldset>
            </div>

            {/* Form actions */}
            <div style={{
                marginTop: '24px',
                paddingTop: '16px',
                borderTop: '1px solid #ddd',
                display: 'flex',
                gap: '12px',
                justifyContent: 'flex-start'
            }}>

                <Button
                    variant="primary"
                    type="submit"
                    isBusy={isSubmitting}
                    disabled={isSubmitting}
                >
                    {isSubmitting ? __('Saving...', 'gregius-data') : submitLabel}
                </Button>

                <Button
                    variant="link"
                    onClick={onCancel}
                    disabled={isSubmitting}
                >
                    {__('Cancel', 'gregius-data')}
                </Button>
            </div>
        </form>
    );
};

export default ConnectionForm;
