import { Router } from "express";

const router = new Router();

import user from "./user";
import token from "./token";
import photo from "./photo";
import csv from "./csv";

const routes = [user, token, photo, csv];

routes.forEach((route) => {
  router.use(route);
});

export default router;
