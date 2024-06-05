import "./database";

import express from "express";
import path from "path";

import cors from "cors";
// import helmet from 'helmet'
import routes from "./routes/index";
import errorHandler from "./middlewares/errorHandler";
import rateLimiter from "./middlewares/rateLimiter";

const whitelist = [];

const corsOptions = {
  origin(origin, callback) {
    if (whitelist.indexOf(origin) !== -1 || !origin) {
      callback(null, true);
    } else {
      callback(new Error("Not allowed by CORS"));
    }
  },
};

const app = express();

app.use(express.urlencoded({ extended: true }));
app.use(express.json());
app.use("/photos", express.static(path.resolve(__dirname, "public", "photos")));
app.use(cors(corsOptions));
// app.use(helmet());

app.use(rateLimiter);
app.use(routes);
app.use(errorHandler);

export default app;
