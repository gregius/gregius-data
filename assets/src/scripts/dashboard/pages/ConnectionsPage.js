/**
 * Connections Page Component
 *
 * Database connection management interface for PostgreSQL connections.
 * Provides CRUD operations, connection testing, and health monitoring.
 */

import { useState, useEffect } from "@wordpress/element";
import { useSelect, useDispatch } from "@wordpress/data";
import { __ } from "@wordpress/i18n";
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Notice,
  Spinner,
  Modal,
  __experimentalHeading as Heading,
} from "@wordpress/components";
import apiFetch from "@wordpress/api-fetch";

// Import connection-specific components
import ConnectionList from "../components/connections/ConnectionList";
import ConnectionForm from "../components/connections/ConnectionForm";

const ConnectionsPage = ({ settings, isLoading, error, apiStatus }) => {
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [editingConnection, setEditingConnection] = useState(null);
  const [testingConnection, setTestingConnection] = useState(null);
  const [testResult, setTestResult] = useState(null);
  const [crudLoading, setCrudLoading] = useState(false);
  const [crudError, setCrudError] = useState(null);
  const [crudSuccess, setCrudSuccess] = useState(null);

  // Use WordPress data stores
  const { connections, isLoadingConnections, connectionsError } = useSelect(
    (select) => ({
      connections: select("gg-data/connections").getConnections(),
      isLoadingConnections: select("gg-data/connections").isLoading(),
      connectionsError: select("gg-data/connections").getError(),
    }),
    [],
  );

  const { addConnection, updateConnectionData, removeConnection } = useDispatch(
    "gg-data/connections",
  );

  // Test connection and health check functions (using apiFetch directly)
  const testConnection = async (connectionName) => {
    try {
      const response = await apiFetch({
        path: `/gg-data/v1/connections/${connectionName}/test`,
        method: "POST",
      });
      return response;
    } catch (error) {
      throw error;
    }
  };

  const getConnectionHealth = async (connectionName) => {
    try {
      const response = await apiFetch({
        path: `/gg-data/v1/connections/${connectionName}/health`,
        method: "GET",
      });
      return response;
    } catch (error) {
      throw error;
    }
  };

  // Handle creating a new connection
  const handleCreateConnection = async (connectionData) => {
    setCrudLoading(true);
    setCrudError(null);
    setCrudSuccess(null);

    // Extract name and access_token from connectionData, rest goes into config
    const { name, access_token, ...configFields } = connectionData;

    // Sanitize config based on provider type
    const providerType = configFields.type || "postgresql";
    const sanitizedConfig = {
      type: providerType,
      connect_timeout: Number(configFields.connect_timeout) || 30,
      description:
        typeof configFields.description === "string"
          ? configFields.description
          : "",
      is_active:
        configFields.is_active === true ||
        configFields.is_active === "true" ||
        configFields.is_active === 1 ||
        configFields.is_active === "1",
    };

    // Add provider-specific fields
    if (providerType === "postgrest") {
      sanitizedConfig.project_url =
        typeof configFields.project_url === "string"
          ? configFields.project_url
          : "";
      sanitizedConfig.publishable_key =
        typeof configFields.publishable_key === "string"
          ? configFields.publishable_key
          : "";
      sanitizedConfig.secret_key =
        typeof configFields.secret_key === "string"
          ? configFields.secret_key
          : "";
    } else {
      // PostgreSQL fields
      sanitizedConfig.host =
        typeof configFields.host === "string" ? configFields.host : "";
      sanitizedConfig.port = Number(configFields.port) || 5432;
      sanitizedConfig.database =
        typeof configFields.database === "string" ? configFields.database : "";
      sanitizedConfig.username =
        typeof configFields.username === "string" ? configFields.username : "";
      sanitizedConfig.password =
        typeof configFields.password === "string" ? configFields.password : "";
      sanitizedConfig.ssl_mode =
        typeof configFields.ssl_mode === "string" ? configFields.ssl_mode : "";
    }

    try {
      // Make API call directly in component - POST expects { name, config, access_token }
      const requestData = {
        name: name,
        config: sanitizedConfig,
      };

      // Add access_token for Supabase connections (one-time use)
      if (providerType === "postgrest" && access_token) {
        requestData.access_token = access_token;
      }

      const response = await apiFetch({
        path: "/gg-data/v1/connections",
        method: "POST",
        data: requestData,
      });

      // Update store with new connection (store expects flat config object)
      if (response.success) {
        addConnection(name, sanitizedConfig);
      }

      setShowCreateModal(false);
      setCrudSuccess(__("Connection created successfully.", "gregius-data"));
    } catch (error) {
      setCrudError(
        error.message || __("Failed to create connection", "gregius-data"),
      );
    } finally {
      setCrudLoading(false);
    }
  };

  // Handle editing an existing connection
  const handleEditConnection = async (connectionName, connectionData) => {
    setCrudLoading(true);
    setCrudError(null);
    setCrudSuccess(null);
    // Sanitize config based on provider type
    // connectionData is already a flat object with all fields directly on it
    const providerType = connectionData.type || "postgresql";
    const sanitizedConfig = {
      type: providerType,
      connect_timeout: Number(connectionData.connect_timeout) || 30,
      description:
        typeof connectionData.description === "string"
          ? connectionData.description
          : "",
      is_active:
        connectionData.is_active === true ||
        connectionData.is_active === "true" ||
        connectionData.is_active === 1 ||
        connectionData.is_active === "1",
    };

    // Add provider-specific fields
    if (providerType === "postgrest") {
      sanitizedConfig.project_url =
        typeof connectionData.project_url === "string"
          ? connectionData.project_url
          : "";
      if (
        connectionData.publishable_key &&
        connectionData.publishable_key !== "***"
      ) {
        sanitizedConfig.publishable_key = connectionData.publishable_key;
      }
      if (connectionData.secret_key && connectionData.secret_key !== "***") {
        sanitizedConfig.secret_key = connectionData.secret_key;
      }
    } else {
      // PostgreSQL fields
      sanitizedConfig.host =
        typeof connectionData.host === "string" ? connectionData.host : "";
      sanitizedConfig.port = Number(connectionData.port) || 5432;
      sanitizedConfig.database =
        typeof connectionData.database === "string"
          ? connectionData.database
          : "";
      sanitizedConfig.username =
        typeof connectionData.username === "string"
          ? connectionData.username
          : "";
      if (connectionData.password && connectionData.password !== "***") {
        sanitizedConfig.password = connectionData.password;
      }
      sanitizedConfig.ssl_mode =
        typeof connectionData.ssl_mode === "string"
          ? connectionData.ssl_mode
          : "";
    }

    try {
      // Make API call directly in component - PUT endpoint expects only { config } in body
      const response = await apiFetch({
        path: `/gg-data/v1/connections/${connectionName}`,
        method: "PUT",
        data: { config: sanitizedConfig },
      });

      // Update store with modified connection
      if (response.success) {
        updateConnectionData(connectionName, sanitizedConfig);
      }

      setEditingConnection(null);
      setCrudSuccess(__("Connection updated successfully.", "gregius-data"));
    } catch (error) {
      setCrudError(
        error.message || __("Failed to update connection", "gregius-data"),
      );
    } finally {
      setCrudLoading(false);
    }
  };

  // Handle deleting a connection
  const handleDeleteConnection = async (connectionName) => {
    if (
      window.confirm(
        __("Are you sure you want to delete this connection?", "gregius-data"),
      )
    ) {
      setCrudLoading(true);
      setCrudError(null);
      setCrudSuccess(null);
      try {
        // Make API call directly in component
        const response = await apiFetch({
          path: `/gg-data/v1/connections/${connectionName}`,
          method: "DELETE",
        });

        // Update store by removing connection
        if (response.success) {
          removeConnection(connectionName);
        }

        setCrudSuccess(__("Connection deleted successfully.", "gregius-data"));
      } catch (error) {
        setCrudError(
          error.message || __("Failed to delete connection", "gregius-data"),
        );
      } finally {
        setCrudLoading(false);
      }
    }
  };

  // Handle testing a connection
  const handleTestConnection = async (connectionName) => {
    setTestingConnection(connectionName);
    try {
      const result = await testConnection(connectionName);
      setTestResult({ connectionName, result });
    } catch (error) {
      setTestResult({
        connectionName,
        result: {
          success: false,
          message:
            error.message || __("Connection test failed", "gregius-data"),
        },
      });
    } finally {
      setTestingConnection(null);
    }
  };

  // Handle starting edit mode
  const handleStartEdit = (connection) => {
    setEditingConnection(connection);
  };

  // Handle canceling edit mode
  const handleCancelEdit = () => {
    setEditingConnection(null);
  };

  // Show loading state
  if (isLoading || isLoadingConnections) {
    return (
      <div className="gg-data-page">
        <Card isRounded={false}>
          <CardBody style={{ textAlign: "center", padding: "40px" }}>
            <p style={{ marginTop: "16px" }}>
              {__("Loading connections...", "gregius-data")}
            </p>
            <Spinner />
          </CardBody>
        </Card>
        {crudLoading && <Spinner style={{ marginTop: "16px" }} />}
        {crudError && (
          <Notice
            status="error"
            isDismissible
            onDismiss={() => setCrudError(null)}
          >
            {crudError}
          </Notice>
        )}
        {crudSuccess && (
          <Notice
            status="success"
            isDismissible
            onDismiss={() => setCrudSuccess(null)}
          >
            {crudSuccess}
          </Notice>
        )}
      </div>
    );
  }

  // Show error state
  if (connectionsError) {
    return (
      <div className="gg-data-page">
        <Notice status="error" isDismissible={false}>
          {__("Failed to load connections: ", "gregius-data") +
            connectionsError.message}
        </Notice>
        {crudError && (
          <Notice
            status="error"
            isDismissible
            onDismiss={() => setCrudError(null)}
          >
            {crudError}
          </Notice>
        )}
        {crudSuccess && (
          <Notice
            status="success"
            isDismissible
            onDismiss={() => setCrudSuccess(null)}
          >
            {crudSuccess}
          </Notice>
        )}
      </div>
    );
  }

  return (
    <div className="gg-data-page">
      {/* Page header with create button */}
      <div className="gg-data-page">
        <div
          style={{
            display: "flex",
            alignItems: "center",
            flexWrap: "wrap",
            justifyContent: "space-between",
            gap: 16,
            padding: "2rem 1.5rem 0",
            borderTop: "1px solid rgba(0, 0, 0, 0.1)",
          }}
        >
          <div style={{ flex: "1" }}>
            <Heading level={2} style={{ margin: 0 }}>
              {__("Connections", "gregius-data")}
            </Heading>
            <p style={{ margin: 0 }}>
              {__(
                "Manage database connections for content synchronization.",
                "gregius-data",
              )}
            </p>
          </div>
          <Button variant="primary" onClick={() => setShowCreateModal(true)}>
            {__("Add Connection", "gregius-data")}
          </Button>
        </div>

        {crudLoading && <Spinner style={{ marginBottom: "16px" }} />}
        {crudError && (
          <Notice
            status="error"
            isDismissible
            onDismiss={() => setCrudError(null)}
          >
            {crudError}
          </Notice>
        )}
        {crudSuccess && (
          <Notice
            status="success"
            isDismissible
            onDismiss={() => setCrudSuccess(null)}
          >
            {crudSuccess}
          </Notice>
        )}

        {/* API status notice */}
        {apiStatus && !apiStatus.success && (
          <Notice status="warning" isDismissible={false}>
            {__(
              "REST API connection issues detected. Some features may not work properly.",
              "gregius-data",
            )}
          </Notice>
        )}

        {/* Connections list (with test results per card) */}
        <ConnectionList
          connections={connections || {}}
          onEdit={handleStartEdit}
          onDelete={handleDeleteConnection}
          onTest={handleTestConnection}
          onAdd={() => setShowCreateModal(true)}
          testingConnection={testingConnection}
          testResults={testResult}
          onDismissTestResult={() => setTestResult(null)}
        />
      </div>

      {/* Create connection modal */}
      {showCreateModal && (
        <Modal
          title={__("Add New Connection", "gregius-data")}
          onRequestClose={() => setShowCreateModal(false)}
          className="gg-data-connection-modal"
        >
          <ConnectionForm
            onSubmit={handleCreateConnection}
            onCancel={() => setShowCreateModal(false)}
            submitLabel={__("Create Connection", "gregius-data")}
          />
        </Modal>
      )}

      {/* Edit connection modal */}
      {editingConnection && (
        <Modal
          title={__("Edit Connection", "gregius-data")}
          onRequestClose={handleCancelEdit}
          className="gg-data-connection-modal"
        >
          <ConnectionForm
            initialData={editingConnection}
            onSubmit={(data) =>
              handleEditConnection(editingConnection.name, data)
            }
            onCancel={handleCancelEdit}
            submitLabel={__("Update Connection", "gregius-data")}
            isEdit={true}
          />
        </Modal>
      )}
    </div>
  );
};

export default ConnectionsPage;
