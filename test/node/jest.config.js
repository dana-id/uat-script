module.exports = {
  // The test environment to use
  testEnvironment: 'node',

  // Configure Jest to use ts-jest for TypeScript files
  preset: 'ts-jest',

  // The glob patterns Jest uses to detect test files
  testMatch: [
    '**/payment_gateway/**/*.ts',
    '**/payment_gateway/**/*.js',
    '**/widget/**/*.ts',
    '**/widget/**/*.js'
  ],

  // An array of regexp pattern strings that are matched against all test paths
  testPathIgnorePatterns: [
    '/node_modules/'
  ],

  testTimeout: 120000,

  // A set of globals that need to be available in all test environments
  globals: {
    // Add any test-wide globals here
  },

  // A list of paths to directories that Jest should use to search for files in
  roots: [
    '<rootDir>'
  ],

  // Setup files that will be executed before each test
  setupFiles: [],

  // Setup files that will be executed after the environment is set up but before each test
  setupFilesAfterEnv: [],

  // Automatically clear mock calls, instances, contexts and results before every test
  clearMocks: true,

  // Indicates whether the coverage information should be collected while executing the test
  collectCoverage: false,

  // The directory where Jest should output its coverage files
  coverageDirectory: "coverage",

  // Verbose output
  verbose: true,
};
