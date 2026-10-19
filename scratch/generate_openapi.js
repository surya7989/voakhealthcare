const fs = require('fs');

// Load canonical list
const { publicEndpoints, adminEndpoints } = (() => {
  const code = fs.readFileSync('scratch/canonical_api_list.js', 'utf8');
  // eval safe in scratch
  const pIdx = code.indexOf('const publicEndpoints =');
  const aIdx = code.indexOf('const adminEndpoints =');
  const endIdx = code.indexOf('console.log(');
  const dataCode = code.substring(pIdx, endIdx) + '\nreturn { publicEndpoints, adminEndpoints };';
  return new Function(dataCode)();
})();

console.log('Generating OpenAPI 3.1 specification for', publicEndpoints.length, 'public and', adminEndpoints.length, 'admin operations...');

const commonErrorResponses = {
  "400": {
    "description": "Validation failure or malformed payload",
    "content": {
      "application/json": {
        "schema": { "$ref": "#/components/schemas/ErrorEnvelope" }
      }
    }
  },
  "401": {
    "description": "Unauthenticated or session expired",
    "content": {
      "application/json": {
        "schema": { "$ref": "#/components/schemas/ErrorEnvelope" }
      }
    }
  },
  "403": {
    "description": "Forbidden - insufficient RBAC permissions",
    "content": {
      "application/json": {
        "schema": { "$ref": "#/components/schemas/ErrorEnvelope" }
      }
    }
  },
  "404": {
    "description": "Resource not found",
    "content": {
      "application/json": {
        "schema": { "$ref": "#/components/schemas/ErrorEnvelope" }
      }
    }
  },
  "429": {
    "description": "Rate limit exceeded",
    "content": {
      "application/json": {
        "schema": { "$ref": "#/components/schemas/ErrorEnvelope" }
      }
    }
  }
};

const paths = {};

const createOperation = (e, isPublic) => {
  const method = e.method.toLowerCase();
  const path = e.path;
  
  if (!paths[path]) {
    paths[path] = {};
  }
  
  // Extract path params
  const pathParams = [];
  const pMatches = [...path.matchAll(/\{([a-zA-Z0-9_]+)\}/g)];
  pMatches.forEach(m => {
    pathParams.push({
      name: m[1],
      in: "path",
      required: true,
      description: `Identifier for ${m[1]}`,
      schema: { type: m[1] === 'id' ? "integer" : "string" }
    });
  });

  // Operation ID
  const cleanPath = path.replace(/\{([a-zA-Z0-9_]+)\}/g, 'By_$1')
                        .split('/')
                        .filter(Boolean)
                        .map(s => s.replace(/[^a-zA-Z0-9]/g, ''))
                        .map(s => s.charAt(0).toUpperCase() + s.slice(1))
                        .join('');
  const opId = method + cleanPath;

  const op = {
    summary: e.summary,
    description: `${e.summary}. Guarded by: ${e.perm || e.auth || 'None'}. Rate limit: ${e.rate || 'Standard'}.`,
    operationId: opId,
    tags: [ isPublic ? "Public" : path.split('/')[2] || "Admin" ],
    parameters: pathParams.length > 0 ? pathParams : undefined,
    responses: {
      [method === 'post' && !path.includes('login') && !path.includes('verify') && !path.includes('test') && !path.includes('retry') && !path.includes('rebuild') ? "201" : (method === 'delete' ? "204" : "200")]: {
        description: method === 'delete' ? "Resource deleted successfully" : "Successful operation response",
        content: method === 'delete' ? undefined : {
          "application/json": {
            "schema": {
              "type": "object",
              "properties": {
                "success": { "type": "boolean", "example": true },
                "data": { "type": "object" },
                "meta": { "type": "object" }
              },
              "required": ["success"]
            }
          }
        }
      },
      ...commonErrorResponses
    }
  };

  // Auth requirements
  if (isPublic || e.perm === 'Public') {
    op.security = [];
  } else if (e.perm === 'Pre-Auth') {
    op.security = [{ "PreAuthToken": [] }];
  } else {
    op.security = [{ "AdminSessionAuth": [], "CsrfToken": [] }];
  }

  // Request body for mutations
  if (['post', 'put', 'patch'].includes(method)) {
    if (path.includes('apply') || path.includes('proposals/submit') || (path.includes('/media') && method === 'post')) {
      op.requestBody = {
        required: true,
        description: "Multipart form data with file upload",
        content: {
          "multipart/form-data": {
            "schema": {
              "type": "object",
              "properties": {
                "file": { "type": "string", "format": "binary" }
              }
            }
          }
        }
      };
    } else {
      op.requestBody = {
        required: true,
        description: "JSON request payload",
        content: {
          "application/json": {
            "schema": {
              "type": "object",
              "properties": {
                "payload": { "type": "object" }
              }
            }
          }
        }
      };
    }
  }

  // Special binary download response for tender and resume
  if (path.endsWith('/download')) {
    op.responses["200"] = {
      "description": "Authenticated binary document stream (PDF/Word/Excel)",
      "content": {
        "application/octet-stream": {
          "schema": { "type": "string", "format": "binary" }
        }
      }
    };
  }

  paths[path][method] = op;
};

publicEndpoints.forEach(e => createOperation(e, true));
adminEndpoints.forEach(e => createOperation(e, false));

const openApiSpec = {
  openapi: "3.1.0",
  info: {
    title: "Voak Health Care Enterprise B2B REST API",
    version: "1.0.0",
    description: "Authoritative OpenAPI 3.1 specification for the Voak Health Care corporate website and administration platform. Strictly compatible with Hostinger shared hosting PHP/HTML runtime.",
    license: {
      name: "Proprietary",
      identifier: "UNLICENSED"
    },
    contact: {
      name: "Voak Health Care IT & Security Operations",
      email: "security@voakhealthcare.com",
      url: "https://voakhealthcare.com"
    }
  },
  servers: [
    {
      url: "https://voakhealthcare.com/api/v1",
      description: "Production Hostinger Environment"
    },
    {
      url: "http://localhost:8000/api/v1",
      description: "Local Development Server"
    }
  ],
  paths: paths,
  components: {
    securitySchemes: {
      AdminSessionAuth: {
        type: "apiKey",
        in: "cookie",
        name: "voak_admin_session",
        description: "HttpOnly, Secure, SameSite=Lax session cookie scoped with Path=/api/v1/admin"
      },
      CsrfToken: {
        type: "apiKey",
        in: "header",
        name: "X-CSRF-Token",
        description: "Synchronizer CSRF token required for all state-changing mutations (POST, PUT, PATCH, DELETE)"
      },
      PreAuthToken: {
        type: "http",
        scheme: "bearer",
        description: "Short-lived (5 min) pre-authentication bearer token issued after valid password check, required for TOTP verification"
      }
    },
    schemas: {
      ErrorEnvelope: {
        type: "object",
        properties: {
          success: { type: "boolean", example: false },
          error: {
            type: "object",
            properties: {
              code: { type: "string", example: "VALIDATION_FAILED" },
              message: { type: "string", example: "One or more fields failed validation" },
              details: {
                type: "array",
                items: {
                  type: "object",
                  properties: {
                    field: { type: "string", example: "corporate_email" },
                    issue: { type: "string", example: "Must be a valid corporate email address" }
                  }
                }
              }
            },
            required: ["code", "message"]
          },
          meta: {
            type: "object",
            properties: {
              timestamp: { type: "string", format: "date-time" },
              correlation_id: { type: "string", example: "req_6711a3b8c9d0e" }
            },
            required: ["timestamp", "correlation_id"]
          }
        },
        required: ["success", "error", "meta"]
      }
    }
  }
};

fs.writeFileSync('docs/phase-02/openapi/openapi.json', JSON.stringify(openApiSpec, null, 2), 'utf8');
console.log('Successfully wrote docs/phase-02/openapi/openapi.json');
console.log('Total paths in openapi.json:', Object.keys(openApiSpec.paths).length);
let opCount = 0;
for (const p of Object.keys(openApiSpec.paths)) {
  opCount += Object.keys(openApiSpec.paths[p]).length;
}
console.log('Total operations in openapi.json:', opCount);
