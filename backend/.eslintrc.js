module.exports = {
  env: {
    browser: true,
    es2021: true,
  },
  extends: ["airbnb-base", "prettier"],
  overrides: [
    {
      env: {
        node: true,
      },
      files: [".eslintrc.{js,cjs}"],
      parserOptions: {
        sourceType: "script",
      },
    },
  ],
  parserOptions: {
    ecmaVersion: "latest",
    sourceType: "module",
  },
  rules: {
    "no-console": "off",
    "class-methods-use-this": "off",
    "import/first": "off",
    "no-param-reassign": "off",
    "no-trailing-spaces": "off",
    "object-curly-newline": "off",
    "consistent-return": "off",
    "no-use-before-define": "off",
    "function-paren-newline": "off",
    "import/no-mutable-exports": "off",
    "prefer-destructuring": "off",
    "no-empty-function": "off",
    "quote-props": "off",
    camelcase: "off",
    quotes: "off",
    "no-unused-expressions": {
      allowTernary: true,
    },
  },
};
