/**
 * Models Page Component
 *
 * Manages AI Model configurations.
 *
 * @since 2.1.0
 */

import { useState, useEffect } from "@wordpress/element";
import { __, sprintf } from "@wordpress/i18n";
import {
  Card,
  CardHeader,
  CardBody,
  Button,
  Modal,
  TextControl,
  SelectControl,
  DropdownMenu,
  Spinner,
  Notice,
  __experimentalGrid as Grid,
  __experimentalHeading as Heading,
} from "@wordpress/components";
import apiFetch from "@wordpress/api-fetch";
import { moreVertical } from "@wordpress/icons";

const ModelsPage = () => {
  const [models, setModels] = useState([]);
  const [providers, setProviders] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [isTesting, setIsTesting] = useState(false);
  const [testResult, setTestResult] = useState(null);

  // Model dimensions mapping
  const MODEL_DIMENSIONS = {
    // OpenAI (Ada-002 excluded - legacy model)
    "text-embedding-3-small": 1536,
    "text-embedding-3-large": 3072,
    // Google Gemini
    "gemini-embedding-2": 3072,
    // Voyage AI
    "voyage-4": 1024,
    // Cohere
    "embed-v4.0": 1536,
    // Internal
    "tfidf-300": 300,
  };

  // Model context window limits (tokens)
  const MODEL_CONTEXT_LIMITS = {
    // OpenAI
    "gpt-3.5-turbo": 16385,
    "gpt-4": 8192,
    "gpt-4-turbo": 128000,
    "gpt-4o": 128000,
    "gpt-4o-mini": 128000,
    // DeepSeek
    "deepseek-chat": 64000,
    "deepseek-coder": 64000,
    "deepseek-reasoner": 64000,
    // Anthropic Claude
    "claude-3-5-sonnet-20241022": 200000,
    "claude-3-5-haiku-20241022": 200000,
    "claude-3-opus-20240229": 200000,
    "claude-3-sonnet-20240229": 200000,
    "claude-3-haiku-20240307": 200000,
    // Google Gemini
    "gemini-2.5-flash": 1048576,
    "gemini-2.5-pro": 1048576,
    "gemini-2.0-flash": 1048576,
    "gemini-2.0-flash-lite": 1048576,
    "gemini-1.5-pro": 2097152,
    "gemini-1.5-flash": 1048576,
    "gemini-1.5-flash-8b": 1048576,
  };

  // Form state
  // Native max output tokens per model (provider hard ceilings)
  const MODEL_MAX_TOKENS = {
    // OpenAI
    "gpt-3.5-turbo": 4096,
    "gpt-4": 8192,
    "gpt-4-turbo": 4096,
    "gpt-4o": 16384,
    "gpt-4o-mini": 16384,
    // DeepSeek
    "deepseek-chat": 8192,
    "deepseek-coder": 8192,
    "deepseek-reasoner": 8192,
    // Anthropic Claude
    "claude-3-5-sonnet-20241022": 8192,
    "claude-3-5-haiku-20241022": 8192,
    "claude-3-opus-20240229": 4096,
    "claude-3-sonnet-20240229": 4096,
    "claude-3-haiku-20240307": 4096,
    // Google Gemini
    "gemini-2.5-flash": 8192,
    "gemini-2.5-pro": 8192,
    "gemini-2.0-flash": 8192,
    "gemini-2.0-flash-lite": 8192,
    "gemini-1.5-pro": 8192,
    "gemini-1.5-flash": 8192,
    "gemini-1.5-flash-8b": 8192,
  };

  // Form state
  const [editingId, setEditingId] = useState(null);
  const [formData, setFormData] = useState({
    id: "",
    type: "llm",
    provider: "openai",
    api_key: "",
    model_name: "",
    max_tokens: "",
    context_window: "",
    dimensions: 1536,
  });

  useEffect(() => {
    fetchData();
  }, []);

  const fetchData = async () => {
    try {
      setIsLoading(true);
      const [modelsData, providersData] = await Promise.all([
        apiFetch({ path: "/gg-data/v1/models" }),
        apiFetch({ path: "/gg-data/v1/models/providers" }),
      ]);

      // Convert object to array if needed (PHP associative array comes as object)
      const modelsArray = Array.isArray(modelsData.data)
        ? modelsData.data
        : Object.values(modelsData.data);

      setModels(modelsArray);
      setProviders(providersData.data);
    } catch (err) {
      setError(err.message);
    } finally {
      setIsLoading(false);
    }
  };

  const getProviderModels = () => {
    const provider = providers.find((p) => p.id === formData.provider);
    if (!provider) {
      return [];
    }

    // Select the correct list based on type
    let modelsList;
    if (formData.type === "embeddings") {
      modelsList = provider.embedding_models;
    } else if (formData.type === "rerank") {
      modelsList = provider.rerank_models;
    } else {
      modelsList = provider.llm_models;
    }

    if (!modelsList) {
      return [];
    }

    // Handle both array (list of strings) and object (slug => name) formats
    if (Array.isArray(modelsList)) {
      return modelsList.map((m) => ({ label: m, value: m }));
    }

    return Object.entries(modelsList).map(([value, label]) => ({
      label,
      value,
    }));
  };

  // Get dimensions for a specific model
  const getDimensionsForModel = (modelName) => {
    return MODEL_DIMENSIONS[modelName] || 1536; // Default to 1536 if not found
  };

  // Check if provider requires API key
  const isApiKeyRequired = () => {
    return formData.provider !== "internal";
  };

  // Get default context window for a model (full model limit)
  const getDefaultContextWindow = (modelName) => {
    if (!modelName) {
      return "";
    }
    const limit = MODEL_CONTEXT_LIMITS[modelName] || 128000;
    return limit;
  };

  // Get default max tokens for a model (native provider ceiling)
  const getDefaultMaxTokens = (modelName) => {
    if (!modelName) {
      return "";
    }
    return MODEL_MAX_TOKENS[modelName] || 8192;
  };

  // Handle model name change and auto-populate dimensions/context_window
  const handleModelNameChange = (modelName) => {
    let newDimensions = formData.dimensions;
    let newContextWindow = formData.context_window;
    let newMaxTokens = formData.max_tokens;

    if (formData.type === "embeddings") {
      // If no model selected (empty string), clear dimensions
      newDimensions = modelName ? getDimensionsForModel(modelName) : "";
    } else {
      // For LLM models, auto-set context_window and max_tokens based on model
      newContextWindow = getDefaultContextWindow(modelName);
      newMaxTokens = getDefaultMaxTokens(modelName);
    }

    setFormData({
      ...formData,
      model_name: modelName,
      dimensions: newDimensions,
      context_window: newContextWindow,
      max_tokens: newMaxTokens,
    });
  };

  /**
   * Get providers that support a given model type.
   *
   * @param {string} type - Model type (llm, embeddings, rerank).
   * @return {Array} Filtered providers.
   */
  const getProvidersForType = (type) => {
    return providers.filter((p) => {
      if (type === "embeddings") {
        return p.capabilities && p.capabilities.includes("embeddings");
      }
      if (type === "rerank") {
        return p.capabilities && p.capabilities.includes("rerank");
      }
      return !p.capabilities || p.capabilities.includes("llm");
    });
  };

  /**
   * Handle model type change.
   * Auto-switches provider if current one doesn't support the new type.
   *
   * @param {string} newType - The new model type.
   */
  const handleTypeChange = (newType) => {
    const validProviders = getProvidersForType(newType);
    const currentProviderValid = validProviders.some(
      (p) => p.id === formData.provider,
    );

    // If current provider doesn't support the new type, switch to first valid provider.
    const newProvider = currentProviderValid
      ? formData.provider
      : validProviders[0]?.id || "openai";

    setFormData({
      ...formData,
      type: newType,
      provider: newProvider,
      model_name: "", // Clear model selection.
      dimensions: "",
      api_key: newProvider === "internal" ? "" : formData.api_key,
    });
  };

  // Handle provider change
  const handleProviderChange = (provider) => {
    setFormData({
      ...formData,
      provider: provider,
      // Clear API key if switching to internal provider
      api_key: provider === "internal" ? "" : formData.api_key,
      // Clear model name, dimensions and tokens when provider changes
      model_name: "",
      dimensions: "",
      context_window: "",
      max_tokens: "",
    });
  };

  const handleTest = async () => {
    setIsTesting(true);
    setTestResult(null);
    try {
      const config = {
        provider: formData.provider,
        api_key: formData.api_key,
      };

      // If editing and key is masked, pass ID so backend can use stored key
      const requestData = {
        config: config,
      };

      if (editingId) {
        requestData.id = editingId;
      }

      const response = await apiFetch({
        path: "/gg-data/v1/models/test",
        method: "POST",
        data: requestData,
      });

      setTestResult({
        success: true,
        message: response.message,
      });
    } catch (err) {
      setTestResult({
        success: false,
        message: err.message,
      });
    } finally {
      setIsTesting(false);
    }
  };

  const handleSave = async () => {
    try {
      setIsSaving(true);
      setError(null);

      // Encode model ID for URL - explicitly encode dots to prevent web server
      // from interpreting them as file extensions (e.g., gpt-3.5-turbo).
      const encodedId = editingId
        ? encodeURIComponent(editingId).replace(/\./g, "%2E")
        : null;
      const path = encodedId
        ? `/gg-data/v1/models/${encodedId}`
        : "/gg-data/v1/models";

      const method = editingId ? "PUT" : "POST";

      const config = {
        provider: formData.provider,
        provider_model_id: formData.model_name,
        model_type: formData.type,
        model_name: formData.model_name,
      };

      if (formData.type === "embeddings") {
        config.dimensions = parseInt(formData.dimensions);
      } else {
        const parsedMaxTokens = parseInt(formData.max_tokens, 10);
        const parsedContextWindow = parseInt(formData.context_window, 10);

        if (Number.isFinite(parsedMaxTokens)) {
          config.max_tokens = parsedMaxTokens;
        }

        if (Number.isFinite(parsedContextWindow)) {
          config.context_window = parsedContextWindow;
        }
      }

      // Only send API key if it's provided and not the masked value
      if (formData.api_key && formData.api_key !== "***") {
        config.api_key = formData.api_key;
      }

      // Build request data - only include id when editing
      const requestData = { config: config };
      if (editingId) {
        requestData.id = editingId;
      }

      await apiFetch({
        path,
        method,
        data: requestData,
      });
      setShowModal(false);
      fetchData();
      resetForm();
    } catch (err) {
      setError(err.message);
    } finally {
      setIsSaving(false);
    }
  };

  const handleDelete = async (id) => {
    if (
      !confirm(
        __("Are you sure you want to delete this model?", "gregius-data"),
      )
    ) {
      return;
    }

    try {
      setIsLoading(true);
      // Encode dots to prevent web server from treating them as file extensions.
      const encodedId = encodeURIComponent(id).replace(/\./g, "%2E");
      await apiFetch({
        path: `/gg-data/v1/models/${encodedId}`,
        method: "DELETE",
      });
      fetchData();
    } catch (err) {
      setError(err.message);
      setIsLoading(false);
    }
  };

  const handleEdit = (model) => {
    const modelId = model.id || model.model_key || "";
    setEditingId(modelId);

    // Handle both old flat structure and new nested config structure
    const provider =
      model.provider || (model.config && model.config.provider) || "openai";
    const api_key =
      model.api_key || (model.config && model.config.api_key) || "";
    const model_name =
      model.model_name || (model.config && model.config.model_name) || "";
    const max_tokens =
      model.max_tokens ||
      (model.config && model.config.max_tokens) ||
      getDefaultMaxTokens(model_name);
    // Use model-specific default: model's full context limit
    const modelLimit = MODEL_CONTEXT_LIMITS[model_name] || 128000;
    const defaultContextWindow = modelLimit;
    const context_window =
      model.context_window ||
      (model.config && model.config.context_window) ||
      defaultContextWindow;
    // Determine type: rerank, embeddings, or llm
    let type = "llm";
    if (model.model_type === "rerank") {
      type = "rerank";
    } else if (model.model_type === "embeddings" || model.dimensions) {
      type = "embeddings";
    }
    const dimensions =
      model.dimensions || (model.config && model.config.dimensions) || 1536;

    setFormData({
      id: modelId.replace("model_", ""), // Strip prefix for display
      type: type,
      provider: provider,
      api_key: api_key,
      model_name: model_name,
      max_tokens: max_tokens,
      context_window: context_window,
      dimensions: dimensions,
    });
    setShowModal(true);
  };

  const resetForm = () => {
    setEditingId(null);
    setTestResult(null);
    setFormData({
      id: "",
      type: "llm",
      provider: "openai",
      api_key: "",
      model_name: "",
      max_tokens: "",
      context_window: "",
      dimensions: "",
    });
  };

  /**
   * Get maximum allowed context window for selected model.
   *
   * @return {number} Maximum context window tokens.
   */
  const getMaxContextWindow = () => {
    if (!formData.model_name) {
      return null;
    }

    return MODEL_CONTEXT_LIMITS[formData.model_name] || 128000;
  };

  const getProviderName = (id) => {
    const provider = providers.find((p) => p.id === id);
    return provider ? provider.name : id;
  };

  const handleResetUsage = async (id) => {
    if (
      !confirm(
        __(
          "Are you sure you want to reset usage stats for this model?",
          "gregius-data",
        ),
      )
    ) {
      return;
    }

    try {
      setIsLoading(true);
      // Encode dots to prevent web server from treating them as file extensions.
      const encodedId = encodeURIComponent(id).replace(/\./g, "%2E");
      await apiFetch({
        path: `/gg-data/v1/models/${encodedId}/reset-usage`,
        method: "POST",
      });
      fetchData();
    } catch (err) {
      setError(err.message);
      setIsLoading(false);
    }
  };

  if (isLoading && !models.length) {
    return (
      <>
        <div className="gg-data-page">
          <div
            style={{
              display: "flex",
              justifyContent: "space-between",
              alignItems: "center",
              marginBottom: "3rem",
              padding: "2rem 1.5rem",
              borderTop: "1px solid rgba(0, 0, 0, 0.1)",
            }}
          >
            <Heading level={2}>{__("AI Models", "gregius-data")}</Heading>
          </div>
        </div>
        <p style={{ marginTop: "16px" }}>
          {__("Loading models...", "gregius-data")}
        </p>
        <Spinner />;
      </>
    );
  }

  return (
    <div className="gg-data-page">
      <div
        style={{
          display: "flex",
          justifyContent: "space-between",
          alignItems: "center",
          padding: "2rem 1.5rem 0",
          borderTop: "1px solid rgba(0, 0, 0, 0.1)",
        }}
      >
        <div style={{ display: "flex", flexDirection: "column" }}>
          <Heading level={2}>{__("AI Models", "gregius-data")}</Heading>
          <p className="description">
            {__("Manage AI models.", "gregius-data")}
          </p>
        </div>
        <div style={{ display: "flex", gap: "10px", alignItems: "center" }}>
          <Button
            variant="primary"
            onClick={() => {
              resetForm();
              setShowModal(true);
            }}
          >
            {__("Add New Model", "gregius-data")}
          </Button>
        </div>
      </div>

      {error && (
        <Notice status="error" isDismissible onRemove={() => setError(null)}>
          {error}
        </Notice>
      )}

      <div
        className="gg-data-model-cards"
        style={{
          display: "grid",
          gridTemplateColumns: "repeat(auto-fill, minmax(400px, 1fr))",
          gap: "20px",
        }}
      >
        {models.map((model) => (
          <Card key={model.id || model.model_key} isRounded={false}>
            <CardHeader>
              <div
                style={{
                  display: "flex",
                  flexWrap: "wrap",
                  gap: "1rem",
                  justifyContent: "space-between",
                  alignItems: "flex-start",
                  width: "100%",
                }}
              >
                <div
                  style={{ display: "flex", alignItems: "center", gap: "12px" }}
                >
                  <Heading level={3} style={{ margin: 0 }}>
                    {model.id || model.model_key}
                  </Heading>
                </div>
                <DropdownMenu
                  icon={moreVertical}
                  label={__("Model actions", "gregius-data")}
                  controls={[
                    {
                      title: __("Edit Model", "gregius-data"),
                      onClick: () => handleEdit(model),
                    },
                    {
                      title: __("Reset Usage", "gregius-data"),
                      onClick: () => handleResetUsage(model.id),
                    },
                    {
                      title: __("Delete Model", "gregius-data"),
                      onClick: () => handleDelete(model.id),
                      className: "has-text-color has-vivid-red-color",
                    },
                  ]}
                />
              </div>
            </CardHeader>
            <CardBody>
              <Grid columns={2} gap={4}>
                <div>
                  <strong>{__("Provider:", "gregius-data")}</strong>{" "}
                  <span className="components-badge is-info">
                    {getProviderName(model.provider)}
                  </span>
                </div>
                <div>
                  <strong>{__("Max Tokens:", "gregius-data")}</strong>{" "}
                  <span className="components-badge is-info">
                    {model.max_tokens || __("N/A", "gregius-data")}
                  </span>
                </div>
                {model.context_window && (
                  <div>
                    <strong>{__("Context Window:", "gregius-data")}</strong>{" "}
                    <span className="components-badge is-info">
                      {model.context_window.toLocaleString()}
                    </span>
                  </div>
                )}
              </Grid>

              {model.usage && (
                <>
                  <hr style={{ margin: "16px 0" }} />
                  <Grid columns={2} gap={4}>
                    <div>
                      <strong>{__("Total Tokens:", "gregius-data")}</strong>{" "}
                      <span className="components-badge is-info">
                        {model.usage.total_tokens.toLocaleString()}
                      </span>
                    </div>
                    <div>
                      <strong>{__("Total Queries:", "gregius-data")}</strong>{" "}
                      <span className="components-badge is-info">
                        {model.usage.total_queries.toLocaleString()}
                      </span>
                    </div>
                    <div>
                      <strong>{__("Last Used:", "gregius-data")}</strong>{" "}
                      <span className="components-badge is-info">
                        {model.usage.last_used_at ||
                          __("Never", "gregius-data")}
                      </span>
                    </div>
                  </Grid>
                </>
              )}
            </CardBody>
          </Card>
        ))}
      </div>

      {models.length === 0 && !isLoading && (
        <Card isRounded={false}>
          <CardBody style={{ textAlign: "center", padding: "60px 40px" }}>
            <p style={{ color: "#646970", marginBottom: "24px" }}>
              {__("Add your first AI Model to get started.", "gregius-data")}
            </p>
            <Button
              variant="secondary"
              onClick={() => {
                resetForm();
                setShowModal(true);
              }}
            >
              {__("Add Your First AI Model", "gregius-data")}
            </Button>
          </CardBody>
        </Card>
      )}

      {showModal && (
        <Modal
          title={
            editingId
              ? __("Edit Model", "gregius-data")
              : __("Add New Model", "gregius-data")
          }
          onRequestClose={() => setShowModal(false)}
        >
          <div
            style={{ display: "flex", flexDirection: "column", gap: "16px" }}
          >
            {/* Model ID (Slug) removed - backend auto-generates from provider_model_id */}
            <SelectControl
              label={__("Model Type", "gregius-data")}
              value={formData.type}
              options={[
                { label: "LLM (Chat)", value: "llm" },
                { label: "Embeddings (Vector)", value: "embeddings" },
                { label: "Rerank", value: "rerank" },
              ]}
              onChange={handleTypeChange}
              __next40pxDefaultSize={true}
              __nextHasNoMarginBottom={true}
            />
            <SelectControl
              label={__("Provider", "gregius-data")}
              value={formData.provider}
              options={getProvidersForType(formData.type).map((p) => ({
                label: p.name,
                value: p.id,
              }))}
              onChange={handleProviderChange}
              __next40pxDefaultSize={true}
              __nextHasNoMarginBottom={true}
            />
            <TextControl
              label={__("API Key", "gregius-data")}
              value={formData.api_key}
              type="password"
              onChange={(val) => setFormData({ ...formData, api_key: val })}
              help={
                !isApiKeyRequired()
                  ? __(
                      "No API key required for internal models",
                      "gregius-data",
                    )
                  : editingId
                  ? __("Leave blank to keep existing key", "gregius-data")
                  : ""
              }
              disabled={!isApiKeyRequired()}
              __next40pxDefaultSize={true}
              __nextHasNoMarginBottom={true}
            />
            {getProviderModels().length > 0 ? (
              <SelectControl
                label={__("Model Name", "gregius-data")}
                value={formData.model_name}
                options={[
                  { label: __("Select a model", "gregius-data"), value: "" },
                  ...getProviderModels(),
                ]}
                onChange={handleModelNameChange}
                help={__("Select the AI model to use.", "gregius-data")}
                __next40pxDefaultSize={true}
                __nextHasNoMarginBottom={true}
              />
            ) : (
              <TextControl
                label={__("Model Name", "gregius-data")}
                value={formData.model_name}
                onChange={handleModelNameChange}
                help={__("e.g., gpt-4o, gpt-3.5-turbo", "gregius-data")}
                __next40pxDefaultSize={true}
                __nextHasNoMarginBottom={true}
              />
            )}
            {formData.type === "llm" && (
              <>
                <TextControl
                  label={__("Max Tokens (Response)", "gregius-data")}
                  value={formData.max_tokens}
                  type="number"
                  onChange={(val) =>
                    setFormData({ ...formData, max_tokens: val })
                  }
                  help={__(
                    "Maximum tokens for the AI response output.",
                    "gregius-data",
                  )}
                  __next40pxDefaultSize={true}
                  __nextHasNoMarginBottom={true}
                />
                <TextControl
                  label={__("Context Window (Input)", "gregius-data")}
                  value={formData.context_window}
                  type="number"
                  onChange={(val) => {
                    const maxAllowed = getMaxContextWindow();

                    if (val === "") {
                      setFormData({ ...formData, context_window: "" });
                      return;
                    }

                    const parsedVal = parseInt(val, 10) || 0;
                    const newVal = maxAllowed
                      ? Math.min(parsedVal, maxAllowed)
                      : parsedVal;
                    setFormData({ ...formData, context_window: newVal });
                  }}
                  help={
                    getMaxContextWindow()
                      ? sprintf(
                          __(
                            "Maximum tokens for RAG context. Model limit: %s tokens. System prompts and response tokens are reserved automatically at runtime.",
                            "gregius-data",
                          ),
                          getMaxContextWindow().toLocaleString(),
                        )
                      : __(
                          "Select a model to see its context window limit.",
                          "gregius-data",
                        )
                  }
                  __next40pxDefaultSize={true}
                  __nextHasNoMarginBottom={true}
                />
              </>
            )}
            {formData.type === "embeddings" && (
              <TextControl
                label={__("Dimensions", "gregius-data")}
                value={formData.dimensions}
                type="number"
                onChange={(val) =>
                  setFormData({ ...formData, dimensions: val })
                }
                help={__(
                  "Automatically set based on model. TF-IDF: 300, OpenAI small: 1536, OpenAI large: 3072",
                  "gregius-data",
                )}
                disabled={true}
                __next40pxDefaultSize={true}
                __nextHasNoMarginBottom={true}
              />
            )}{" "}
            {testResult && (
              <Notice
                status={testResult.success ? "success" : "error"}
                isDismissible={false}
                style={{ margin: "0" }}
              >
                {testResult.message}
              </Notice>
            )}
            <div
              style={{
                display: "flex",
                gap: "1rem",
                marginTop: "1rem",
                alignItems: "center",
              }}
            >
              <Button variant="primary" onClick={handleSave} isBusy={isSaving}>
                {__("Save Model", "gregius-data")}
              </Button>
              <Button
                variant="secondary"
                onClick={handleTest}
                isBusy={isTesting}
                disabled={!isApiKeyRequired()}
              >
                {__("Test Connection", "gregius-data")}
              </Button>
              <Button
                variant="tertiary"
                onClick={() => setShowModal(false)}
                style={{ marginLeft: "auto" }}
              >
                {__("Cancel", "gregius-data")}
              </Button>
            </div>
          </div>
        </Modal>
      )}
    </div>
  );
};

export default ModelsPage;
