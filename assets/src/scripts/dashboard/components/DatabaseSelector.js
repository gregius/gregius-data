/**
 * DatabaseSelector Component
 * 
 * WordPress-native database connection selector using @wordpress/components
 * Replaces custom HTML select with accessible SelectControl component.
 */

import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { SelectControl } from '@wordpress/components';
import PropTypes from 'prop-types';

/**
 * DatabaseSelector Component
 * Allows user to select the active database connection for content, schema, and sync management.
 * 
 * @param {Object} props - Component props
 * @param {Array} props.connections - Array of connection objects { id, name, is_active, is_default }
 * @param {string} props.selectedConnectionId - Currently selected connection name (not ID)
 * @param {Function} props.onSelect - Callback function(connectionName) => void
 * @returns {JSX.Element} SelectControl component
 */
export default function DatabaseSelector({ connections, selectedConnectionId, onSelect }) {
  // Normalize value to string, handle undefined/null cases
  let value = '';
  if (
    selectedConnectionId !== undefined &&
    selectedConnectionId !== null &&
    selectedConnectionId !== 'undefined'
  ) {
    value = String(selectedConnectionId);
  }
  if (value === 'undefined') value = '';
  
  // Format options for SelectControl
  const options = [
    { 
      label: __('Select a connection...', 'gregius-data'), 
      value: '',
      disabled: false
    },
    ...connections.map(conn => {
      // Use connection name (not ID) for API calls
      const optionValue = conn.name ? String(conn.name) : '';
      
      // Build label with status indicators
      const statusParts = [conn.name];
      if (conn.is_default) statusParts.push(__('(Default)', 'gregius-data'));
      if (conn.is_active === false) statusParts.push(__('(Inactive)', 'gregius-data'));
      
      return {
        label: statusParts.join(' '),
        value: optionValue,
        disabled: conn.is_active === false
      };
    })
  ];
  
  // Handle selection change
  const handleChange = (newValue) => {
    // Normalize value before passing to parent
    let v = newValue;
    if (v === undefined || v === null || v === 'undefined') v = '';
    onSelect(v);
  };
  
  return createElement(SelectControl, {
    label: __('Connection', 'gregius-data'),
    value,
    options,
    onChange: handleChange,
    __next40pxDefaultSize: true,
    __nextHasNoMarginBottom: true
  });
}

DatabaseSelector.propTypes = {
  connections: PropTypes.array.isRequired,
  selectedConnectionId: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
  onSelect: PropTypes.func.isRequired,
};
