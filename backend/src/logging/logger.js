import pino from "pino";

const transport = pino.transport({
  target: process.env.NODE_ENV === "production" ? "pino/file" : "pino-pretty",
  options: {
    destination: `${__dirname}/logs.log`,
    mkdir: true,
    colorize: process.env.NODE_ENV !== "production",
  },
});

const logger = pino(
  {
    base: undefined,
    level: process.env.NODE_ENV === "production" ? "warn" : "debug",
    redact: {
      paths: ["email", "password"],
      censor: "[RESTRICTED]",
    },
    timestamp:
      process.env.NODE_ENV === "production"
        ? () => `"timestamp":"${new Date(Date.now()).toISOString()}"`
        : true,
    formatters: {
      level: (label) => ({ level: label.toUpperCase() }),
    },
  },
  transport
);

export default logger;
